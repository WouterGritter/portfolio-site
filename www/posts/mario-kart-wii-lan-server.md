<!-- title = Mario Kart Wii on a LAN server -->
<!-- longtitle = Self-hosting a Mario Kart Wii server for LAN play, with Dolphin and a real Wii -->
<!-- postdate = 9th of September 2026 -->
<!-- description = Running a private WiiLink WFC server on my homelab so Mario Kart Wii multiplayer works entirely on the LAN, between Dolphin instances and an unmodified Wii. Covers the three-stage patch chain, the docker compose stack, the two bugs that cost the most time, and what it takes on both kinds of client. -->

# @longtitle

_Posted on @postdate._

Nintendo shut down Wi-Fi Connection in May 2014, so Mario Kart Wii's online mode has been dead for over a decade.
The game itself is fine though: the race netcode is peer-to-peer, and Nintendo's servers only ever did
authentication, matchmaking and stats. Community projects like Wiimmfi and WiiLink WFC rebuilt those servers
and patch the game to point at them.

I wanted to run one of those servers myself, on my homelab, so I could race between two gaming PCs running
Dolphin and a real Wii, without anything leaving the house. This post is what that took. Everything is in
[WouterGritter/mkwii on GitHub](https://github.com/WouterGritter/mkwii), including proper setup docs, so if
you just want it working, start there. This is the story behind it.

A few constraints I set for myself up front:

- **Nothing on the console gets modified.** No NAND edits, no disc patches, no Riivolution. The patch is
  applied in memory at launch, so pulling the SD card leaves the Wii exactly as it was.
- **LAN-only, for now,** but using a domain I own, so it can go public later by changing a DNS record rather
  than rebuilding anything.
- **One `docker-compose.yml`** on a VM, Postgres included.
- **My own DNS.** I run dnsmasq for the house, so the game should be steered there. No hosts files, no NAND
  DNS setting.

---

### How the game gets redirected

[WiiLink WFC](https://github.com/WiiLink24/wfc-server) is the open-source server: Go plus PostgreSQL,
reimplementing the GameSpy stack Nintendo licensed (login, friend search, server browser, NAT negotiation,
stats) plus Nintendo's own auth and storage endpoints. The client side is
[wfc-patcher-wii](https://github.com/WiiLink24/wfc-patcher-wii), a PowerPC cross-compiled patch chain. I keep
both as pinned git submodules and never edit them; my changes are overlays applied at build time.

The patch chain is the single most interesting thing to understand, and it drives every design decision after it.

| Stage | What it is | Where it lives |
|---|---|---|
| **0** | A Gecko code applied to the running game. Rewrites the auth URL `https://naswii.nintendowifi.net/ac` to `http://nas.<domain>/w1`. Embeds an **MD5 of stage 1** and an **RSA-2048 public key**. | Compiled into every client. |
| **1** | About 3.5 KB of position-independent code, fetched from `/w1`. Downloads stage 2 and **verifies its RSA signature** against the key stage 0 trusts. | Served by the server. |
| **2** | The game-specific runtime payload, about 22 KB for Mario Kart Wii. Hooks `gethostbyname` and rewrites *every* DNS lookup: `nintendowifi.net` becomes `<domain>`, `gamespy.com` becomes `gs.<domain>`. | Served by `/payload`, **signed at request time**. |

Some consequences that took a while to sink in:

- **The domain is compiled into stage 0 and stage 2.** Changing it means rebuilding and redistributing every
  client. Where it *points* is just DNS, so going from LAN to public later needs no client rebuild.
- **The domain can't be longer than 16 characters.** The payload rewrites hostnames in place inside the game's
  fixed-size buffers, and `nintendowifi.net` is 16 characters. My first candidate domain got rejected by a
  `static_assert` before anything else happened; `wfc.gritter.me` is 14.
- **The signing key is forever.** Stage 2 can be updated server-side at will, since it's fetched and verified
  on every connect, but the keypair is compiled into stage 0. The private key is the one file that must never
  be committed and must be backed up.
- Everything is plain HTTP. Stage 0 rewrites `https://` to `http://`, which sidesteps the Wii's ancient SSL
  stack. Integrity comes from the MD5 and RSA chain, not the transport, so there are no certificates anywhere.

The signing is also more clever than I expected. The client sends a random salt and a short hash proving it
built the URL itself; the server writes a hash of that into the payload header, then signs the whole thing.
Every payload download is unique and bound to the request that asked for it. Keep that in mind, because it's
where the first real bug lives.

---

### Building the client

Building for Wii needs devkitPPC, and devkitPro's package hosts return a Cloudflare 403 from my network no
matter what I tried. So everything PowerPC-related builds inside their `devkitpro/devkitppc` Docker image, which
also made the build reproducible by construction: a clean `git clone --recurse-submodules` plus a `config.env`
with the domain reproduces every artifact byte-for-byte, except the signing key.

One early snag: the patcher's README says stage 1 is served from `/w0`. The actual stage 0 source requests
`/w1`, and the server maps that to `stage1v1.bin`, which the build didn't produce. Result: a 404 on the very
first request, and the server logs nothing for it. That lesson came back several times: **the README is stale,
the source is the spec.**

---

### The server

The stack is Postgres 16 plus the WWFC image, built from the pinned submodule with an overlay applied. Two
things cost time here that weren't obvious.

**`network_mode: host` is mandatory.** The server keys each session by the UDP source address it observes
and hands that address to the other players, so they can open the peer-to-peer race connection. Behind Docker's
bridge NAT every client appears to come from the Docker gateway. Login works, matchmaking works, and then nobody
can reach anybody.

**Upstream's `schema.sql` is a pg_dump.** It contains `ALTER TABLE ... OWNER TO wiilink`, and Postgres runs
init scripts with `ON_ERROR_STOP`, so without a `wiilink` role the import aborts halfway and you get a database
with some of the tables. A tiny script that runs first creates the role, and the upstream file stays untouched.

The overlays follow one rule: apply an exact-text replacement to a pristine clone at build time, and **fail the
build if the expected text isn't found exactly once**. An upstream update either applies cleanly or refuses
loudly.

For the firewall, from the client VLAN to the server:

| Port | Proto | Service |
|---|---|---|
| 80 | TCP | Auth, stage 1 and stage 2 download |
| 28910 | TCP | Server browser |
| 29900 | TCP | GPCM (login / presence) |
| 29901 | TCP | GPSP (friend search) |
| 29920 | TCP | GameStats |
| 27900 | UDP | QR2 heartbeat and address observation |
| 27901 | UDP | NAT negotiation |

The race traffic itself is peer-to-peer UDP on dynamic ports between the clients and never touches the server.

---

### DNS: one line

The game resolves eleven names, some five labels deep (`mariokartwii.master.gs.wfc.gritter.me`, for instance).
dnsmasq's `address=` matches the domain and every name under it at any depth, so the entire DNS side is:

```
address=/wfc.gritter.me/<server-ip>
```

Going public later is one wildcard A record and a firewall rule.

**Dolphin has no DNS setting.** The emulated Wii's `gethostbyname` calls the *host's* resolver, so Dolphin just
needs the PC to use my DNS, which it already does via DHCP. If you have DNS-over-HTTPS turned on in Windows,
that bypasses your resolver and this zone doesn't exist publicly, so turn it off.

---

### First contact, first bug: `Salt hash mismatch`

Dolphin, PAL disc, Gecko code enabled, click "Nintendo WFC". Server log:

```
E[NAS:10.x.y.z:54027]: Salt hash mismatch
```

So stage 0 ran, stage 1 was downloaded and executed (it's the thing that makes the salted request), and the
server refused stage 2. Reading both ends: stage 1 on the patcher's `main` branch builds the query as
`payload?d=<deviceid>&g=RMCPD00&s=<salt>` and hashes **that whole string** for the proof. The server
reconstructs the string it *thinks* the client hashed from parsed parameters:

```go
saltHashData := "payload?g=" + query["g"][0] + "&s=" + query["s"][0]
```

No `d=`. The hashes never match, so every current client is refused by the current server. The two repos had
drifted apart.

My first fix was to build the client from the patcher's `release` branch instead, whose stage 1 predates the
`d=` parameter. Salt hash matched. Onward.

---

### Second bug: the game hangs

With the `release` client, the "Connecting to Nintendo WFC" animation froze halfway. This one needed
instruments, not reading.

A packet capture on the server (`tcpdump -i any -U -w ...`) showed `POST /w1` returning 200, then
`GET /payload?g=..&s=..&h=..` returning 200 with the full 22 KB. The server did its whole job, so the crash was
in the client, after the download. Side note: without `-U` tcpdump buffers, and reading the pcap mid-capture
shows a truncated transfer that looks exactly like a stalled download. I chased that for a bit.

Then Dolphin's log. "Show Log" opens an empty window; logging is off until you go to View, Log Configuration,
set a verbosity, and tick `OSReport HLE`. With that on, the game's own exception handler printed:

```
ISI exception at 0x0011C664
```

An ISI is an instruction fetch from an unmapped address, and `0x0011C664` is a MEM1 address **missing its
`0x80000000` prefix**. Subtract stage 2's entry point offset (`0xA3C`) and you get the base pointer stage 1
used for the payload block, with the top bit stripped. The older stage 1 on `release` computes the entry
address wrong: it downloads and verifies stage 2 correctly, then branches into nothing.

So `release` was broken and `main` was incompatible with the server. The resolution: build from `main`, and fix
the *server* instead:

```go
// Hash the raw query string the client actually built, up to the &h= it
// appended last. Don't re-serialise it from parsed parameters - that
// silently drops any parameter this server doesn't know about.
rawQuery := u.RawQuery
if i := strings.Index(rawQuery, "&h="); i >= 0 {
    rawQuery = rawQuery[:i]
}
saltHashData := "payload?" + rawQuery
```

That's the whole server overlay. Ten lines, and it stays correct if the parameter set changes again.

The thing worth taking away: both real bugs were version skew between two repos maintained by the same project,
and neither was findable by reading one repo in isolation. The server was correct for the client it was written
against, and the client was correct for the server it was written against. The pairing had drifted.

---

### Verifying without a console

`verify-chain.py` runs from the builder container against the live server and walks the chain in the order a
client would. Sixteen checks in about a second: stage 1 is served at the version stage 0 asks for, stage 0
embeds the MD5 of the stage 1 actually being served, stage 0's trusted public key matches the server's signing
key, stage 2's signature verifies, and a salted request the way a real client sends it is accepted while a
wrong salt hash is refused.

That last one matters. An earlier version fetched `/payload` *without* a salt, and the unsalted path on the
server skips the check, so it passed the whole time the first bug was live. The instrument had a blind spot
exactly where the bug was.

---

### The Dolphin clients

Both gaming PCs run Dolphin with the PAL disc and a per-game INI that adds the stage 0 Gecko code. A few traps:

- **Config, General, Enable Cheats** defaults to off, and Gecko codes are part of the cheat engine. With it off,
  Dolphin shows the code as enabled and applies nothing. An error about `clientca.pem` means the same thing: the
  game is still on the original HTTPS path, so stage 0 didn't apply.
- A leading `*` in an INI `[Gecko]` section is a note, not a code line. My first INI generator prefixed every
  line, and the code showed as enabled and did nothing.
- Worldwide matchmaking with exactly two players takes a minute or two. The game alternates between searching
  for a room and offering to host one, and two clients only pair up when they land in complementary phases.
  **Friend rooms skip this entirely.** Exchange friend codes once and joining is instant.

One of the two PCs has a Logitech G920 wheel attached, which turns out to be a great way to play Mario Kart Wii
once the mapping is right. I wrote up the Dolphin profile for that separately, including a trick input that
mimics yanking a real Wii wheel:
[Getting a Logitech G920 working with Mario Kart Wii on Dolphin](/posts/g920-mario-kart-wii-dolphin.md).

---

### The real Wii

The plan was upstream's Homebrew Channel loader app, which reads the disc, patches it in memory and boots it.
It hangs at a black screen right after "Reading the disc", in upstream's own disc-boot path, before any WWFC
code runs. I stopped chasing it; the notes on what's been ruled out are in the repo for anyone who wants to.

What actually works is **Gecko OS**, which applies cheat codes to a retail disc at launch. That's precisely what
stage 0 *is*. So the same Gecko code Dolphin uses, assembled into the binary `.gct` container (an
`00D0C0DE 00D0C0DE` header, the code words, an `F0000000` terminator; renaming the text file does not do this),
goes to `SD:/codes/RMCP01.gct`. Then Homebrew Channel, Gecko OS, SD Cheats on, launch game, Nintendo WFC.

Two things that bit me here again:

- The codes must be on an **SD card**, not USB. The Gecko OS option is literally called *SD Cheats*, and codes
  on USB are widely reported not to load.
- The filename is `RMCP01.gct`, the 6-character Wii title ID. The patcher calls the same disc `RMCPD00` (`D` for
  disc, `00` for revision). Same disc, two naming schemes.

This route ended up nicer than the loader would have been. Stage 1 is downloaded from `/w1` at runtime,
exactly as on Dolphin, so the real Wii reuses the one client path already proven end to end, and its progress is
visible in a server-side capture: `POST /w1`, `GET /payload`, `POST /ac`, login. With a friend code registered
on both sides, the Wii and a Dolphin instance join the same room and race.

Mine is wired. If yours has to be on Wi-Fi, its radio does 802.11g on 2.4 GHz with WPA2-PSK at best, so a
WPA2/WPA3 mixed-mode SSID with PMF required will stop it from associating at all.

---

### Wrapping up

That's two Dolphin instances and a real Wii racing each other over the LAN, with nothing leaving the house and
nothing modified on the console. The server has been running since, and its only involvement in a race is
having told each console the other's address.

---

### Sources

- [WouterGritter/mkwii](https://github.com/WouterGritter/mkwii): the tooling, compose stack, overlays and setup
  docs for Dolphin, a real Wii and the server.
- [WiiLink24/wfc-server](https://github.com/WiiLink24/wfc-server) and
  [WiiLink24/wfc-patcher-wii](https://github.com/WiiLink24/wfc-patcher-wii): the upstream server and patch
  chain. Note the patcher's README refers to `/w0` where the source uses `/w1`, and `main` and `release` are not
  interchangeable (see above).
