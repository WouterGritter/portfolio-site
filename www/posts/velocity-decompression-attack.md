<!-- title = Mitigating Velocity decompression attack -->
<!-- longtitle = Responding to a live decompression attack on Velocity proxies -->
<!-- postdate = 22nd of March 2026 -->
<!-- description = A write-up of a live decompression bomb attack targeting Velocity Minecraft proxies, how I identified it, mitigated it in real time, and contributed fixes to Velocity. -->

# @longtitle

_Posted on @postdate._

A few days ago I was helping diagnose a situation where Minecraft proxy servers were being repeatedly OOM killed -
crashing within seconds of coming back online. This write-up covers how I identified the attack vector,
the short-term fix I put together under fire, and the proper fixes that made it upstream to
[PaperMC/Velocity](https://github.com/PaperMC/Velocity).


### What was happening

The proxies were running [Velocity-CTD](https://github.com/GemstoneGG/Velocity-CTD), a popular fork of the Minecraft proxy Velocity.
Every few seconds, memory would jump from a few gigabytes to well above the container limit, and the process would get killed.
On restart it would die again almost immediately - the server was effectively offline.

A Wireshark packet capture of the proxy's network interface revealed a handful of IPs generating a
disproportionate share of traffic, but nothing that obviously screamed "attack" just from raw packet counts.
One IP stood out as having the most traffic by far, but it turned out to just be a player with several accounts
online at the same time. The packet capture did hint at potential SYN flood behaviour, but that wasn't the real story.

The real clue came from the Java heap dumps taken seconds before the crashes. Combined with inspecting
Velocity's compression pipeline, my suspicion landed on a decompression bomb: tiny compressed packets that
expand to nearly the full allowed uncompressed size. Send enough of them fast enough, and the proxy can't keep up.


### Instrumenting the code

To confirm this, I added logging to `MinecraftCompressDecoder#decode` in Velocity's pipeline - the spot
where incoming compressed packets are decompressed before being handed off for processing:

```java
if (claimedUncompressedSize > UNCOMPRESSED_CAP * 0.95) {
    LOGGER.warn("Received a packet with a large uncompressed size: {} bytes ({}% of the {} byte cap) from {}",
        claimedUncompressedSize,
        (int) (claimedUncompressedSize * 100.0 / UNCOMPRESSED_CAP),
        UNCOMPRESSED_CAP,
        ctx.channel().remoteAddress());
}
```

I deployed a custom build with this logging to the live proxy and waited. Within seconds of the proxy coming
back up, the logs lit up:

```
[17:36:45 WARN]: Received a packet with a large uncompressed size: 8380416 bytes (99% of the 8388608 byte cap) from /x.x.x.x:16643
[17:36:45 WARN]: Received a packet with a large uncompressed size: 8380416 bytes (99% of the 8388608 byte cap) from /x.x.x.x:16643
[17:36:45 WARN]: Received a packet with a large uncompressed size: 8380416 bytes (99% of the 8388608 byte cap) from /x.x.x.x:16643
...
```

Same IP, same exact size, over and over at an enormous rate. The attack was confirmed: a single IP was
hammering the proxy with compressed packets that decompressed to 8,380,416 bytes - 99% of Velocity's
8 MiB uncompressed cap - on every single packet.

To decompress each packet, Velocity has to allocate a buffer sized to the claimed uncompressed size.
With no rate limiting on this, a single attacker could force the proxy to allocate gigabytes of memory
per second, triggering the OOM kill.


### Immediate mitigation

With the attacker's IP identified, blocking it at the firewall stopped the crashing immediately. The proxies
stayed up once that single IP was blocked.

That said, it was only a matter of time before the attacker switched IPs - blocking one address is not a fix.
And given that other Minecraft networks appeared to be going down with the same symptoms at the same time,
this looked like a coordinated or scripted attack, not a one-off.


### Short-term fix: patch the fork

The proxy was running a custom fork of Velocity. While the firewall block bought some time, I put together a
proper mitigation commit to the fork to handle this at the application layer:
[GemstoneGG/Velocity-CTD@3fd2f11](https://github.com/GemstoneGG/Velocity-CTD/commit/3fd2f11b3b35d066f2ee064e1f4b7c785b709997)

Inside `MinecraftCompressDecoder#decode`, for player-facing connections, I added checks for two
conditions that indicate a likely decompression bomb:

1. The uncompressed size is above 95% of the 8 MiB hard cap.
2. The compression ratio (uncompressed / compressed) exceeds 1024:1.

A per-connection counter tracks how many such oversized packets have been received. After 10, the connection
is forcibly closed:

```java
int compressedSize = in.readableBytes();
if (playerFacing && (claimedUncompressedSize > compressedSize * MAX_COMPRESSION_RATIO
    || claimedUncompressedSize > UNCOMPRESSED_CAP * 0.95)) {
    oversizedPacketCount++;
    if (oversizedPacketCount > OVERSIZED_PACKET_LIMIT) {
        LOGGER.warn("Disconnecting {} for sending too many oversized packets ({} packets near the {} byte cap)",
            ctx.channel().remoteAddress(), oversizedPacketCount, UNCOMPRESSED_CAP);
        ctx.close();
        return;
    }
    LOGGER.warn("Received a packet with a large uncompressed size: {} bytes ({}% of the {} byte cap, {}:1 ratio) from {} [{}/{}]",
        claimedUncompressedSize,
        (int) (claimedUncompressedSize * 100.0 / UNCOMPRESSED_CAP),
        UNCOMPRESSED_CAP,
        compressedSize > 0 ? claimedUncompressedSize / compressedSize : "∞",
        ctx.channel().remoteAddress(),
        oversizedPacketCount,
        OVERSIZED_PACKET_LIMIT);
}
```

With this deployed, the firewall block was no longer needed. The proxy would detect the attack and close the
connection itself. The thresholds (95% of cap, 1024:1 ratio, 10 packet limit) aren't scientifically derived,
but they worked: no legitimate connections were affected, and the attack was stopped.


### Recreating the attack locally

With a fix deployed, the proxy stayed up - but the real attacks were intermittent. I couldn't easily
tell whether the fix was genuinely working or whether the attacker had just moved on. To verify the
mitigation was sound, I needed a reliable way to reproduce the attack on demand.

I put together a proof-of-concept Fabric client mod. The core of it is a Mixin on
`ClientConfigurationPacketListenerImpl` that intercepts `handleConfigurationFinished` and cancels it,
preventing the client from ever sending `ServerboundFinishConfigurationPacket`. The connection stays
stuck in CONFIG state indefinitely.

From there, the PoC repeatedly sends oversized unknown packets by writing raw bytes directly into the
Netty encoder, bypassing Minecraft's normal packet serialization:

```java
ByteBuf buf = Unpooled.buffer(TARGET_SIZE);
writeVarInt(buf, 0x7E);  // unknown packet ID in CONFIG state
buf.writeZero(TARGET_SIZE - buf.readableBytes());
encoderCtx.writeAndFlush(buf);
```

Packet ID `0x7E` doesn't exist in the CONFIG protocol. The payload is padded to 99% of Velocity's 8 MiB
uncompressed cap. The Minecraft client's compression pipeline compresses all those zeros down to almost
nothing before it goes on the wire - so the packet arrives at Velocity as a tiny blob with a large
claimed uncompressed size.

Velocity decompresses it, allocating ~8 MB per packet. Then it tries to dispatch it as a CONFIG-state
packet. Before the fix, unknown packets in the login and config states weren't rejected - Velocity would
just pass them through, meaning nothing stopped the allocation from happening on every single packet.
Flooding the proxy with these at any reasonable rate was enough to exhaust memory within seconds.

This is what PR #1743 specifically addresses with its unknown packet rejection: packets with unrecognised
IDs during login and config are now rejected outright, and the connection is closed. With that in place,
the PoC's first packet kills the connection rather than leaking memory.


### Getting fixes into upstream Velocity

I filed [issue #1742](https://github.com/PaperMC/Velocity/issues/1742) on the upstream Velocity repository
describing the attack and the approach I had taken in the fork. Two pull requests followed.

#### PR #1743 - Broader protocol safeguards (not by me)

[PR #1743](https://github.com/PaperMC/Velocity/pull/1743) was opened by another contributor in response to
the issue. Rather than just addressing the decompression problem, it introduced a wider set of protocol-level
safeguards:

- **Inbound packet queue size limit** - a cap (128 MiB by default, configurable via
  `velocity.maximum-play-queue-size`) on the total size of queued packets on a connection. This limits how
  much memory an attacker can force Velocity to hold in queue before a connection is drained.
- **Unknown packet rejection during login/config** - packets with unknown IDs during the login and config
  protocol states are now rejected outright instead of being passed through, closing off a related class of
  abuse.
- **Per-packet size bounds on specific packet types** - `decodeExpectedMaxLength` and
  `decodeExpectedMinLength` were implemented for several packet types (`KeepAlive`, `ClientSettings`,
  `PluginMessage`, `ResourcePackResponse`, `PingIdentify`, `ServerboundCookieResponse`), making it harder to
  send anomalously large packets through these handlers.

This PR was merged and provides a solid layer of defence beyond just the decompression case.

#### PR #1745 - Missing writabilityChanged() implementations (by me)

[PR #1745](https://github.com/PaperMC/Velocity/pull/1745) is the second upstream contribution, and it's
related but more subtle.

Velocity has a `velocity.log-server-backpressure` flag for diagnosing situations where a connection is
falling behind - where the write buffer is full and the proxy should pause reading from the other side.
This backpressure logging already existed in `BackendPlaySessionHandler`, but was entirely absent from
`ConfigSessionHandler` and `ClientConfigSessionHandler`. Those handlers had no `writabilityChanged()`
implementation at all, meaning backpressure during the config phase of the connection lifecycle was silently
ignored.

My PR adds `writabilityChanged()` to both of those handlers. Each implementation reads the writability state
of the relevant channel and, when `BACKPRESSURE_LOG` is enabled, logs whether the connection is writable or not:

```java
// ConfigSessionHandler.java
@Override
public void writabilityChanged() {
    Channel serverChan = serverConn.ensureConnected().getChannel();
    boolean writable = serverChan.isWritable();

    if (BACKPRESSURE_LOG) {
        if (writable) {
            logger.info("{} is writable, will auto-read player connection data", this.serverConn);
        } else {
            logger.info("{} is not writable, not auto-reading player connection data", this.serverConn);
        }
    }
    // ... auto-read toggling
}
```

The same pattern was added for `ClientConfigSessionHandler` and `ClientPlaySessionHandler`. This isn't a
direct fix to the decompression attack, but it closes a visibility gap: if a future incident involves
backpressure building up during a connection's config phase, this logging will now surface it.

Both PRs were reviewed and merged.


### Takeaways

A few things that made this incident easier to deal with in the moment:

**Debug builds are worth having.** Being able to drop in a custom build with added logging - and get useful
output within minutes - was what turned "proxy keeps dying" into "we know exactly who is attacking and how."
Without that instrumentation step, the investigation would have taken much longer.

**Firewall blocks are not a fix.** They're useful for buying time, but a determined attacker changes IPs.
The goal should always be to handle the attack at the application layer, so infrastructure stays up regardless
of where the traffic comes from.

**Application-level rate limiting on expensive operations matters.** Velocity already had a hard cap on
uncompressed packet size (8 MiB), which is a good start. But the cap alone doesn't protect against an
attacker repeatedly sending packets that hit that cap - it just means each individual packet is "valid".
A per-connection count of suspicious packets, with a threshold that triggers a disconnect, was the missing piece.

**File issues upstream.** Networks running this kind of software aren't isolated - if one is being attacked,
others likely are too. Filing the issue got a broader fix into Velocity for everyone, not just the fork.
