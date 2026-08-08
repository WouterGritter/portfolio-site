<!-- title = KPN routed IPTV on pfSense -->
<!-- longtitle = Getting a KPN TV+ box working behind your own pfSense router -->
<!-- postdate = 8th of August 2026 -->
<!-- description = KPN's TV+ box uses routed IPTV over multicast on a separate VLAN. Here's how to get it working behind pfSense (with your own bridge modem), including the DHCP, NAT, firewall and IGMP proxy bits the guides get wrong. -->

# @longtitle

_Posted on @postdate._

I run my home network on pfSense, not KPN's Experiabox. That's fine for internet, but the KPN TV+ IPTV box
is a different beast: KPN delivers TV as **routed IPTV over multicast**, and the box expects to talk to KPN's
TV platform on a VLAN that the Experiabox normally handles for you. Replace the Experiabox and you inherit that job.

This post is how I got it working. It's pfSense-specific, but the concepts map cleanly onto OPNsense too.

---

### The actual problem

You might assume the issue is NAT-behind-NAT. It isn't. The real problem is that KPN splits your line into
separate VLANs, and the TV box needs one you don't normally touch:

- **VLAN 6** carries internet + VoIP (PPPoE).
- **VLAN 4** carries the TV signal: routed IPTV, delivered as multicast.

Your internet already lives on VLAN 6. To get TV working you need pfSense to bring up **VLAN 4**, pull a DHCP
lease from KPN on it, and then route/forward the multicast TV streams from that VLAN down to the box on your LAN.
That's the whole game.

---

### Prerequisites

- **Your own bridge modem.** This is non-negotiable. The Experiabox does **not** support bridge mode, so you
  need a separate VDSL modem that hands the raw, tagged line straight to pfSense. All the VLANs (4 and 6) arrive
  on that single link. I use a ZyXel VMG4005-B50A.
- **Internet already working.** This post assumes your WAN is already up (VLAN 6, PPPoE) and you just want to
  add TV. I won't cover the internet side here.

For the examples I'll use placeholder values:

- Bridge modem plugged into a physical pfSense port, say `igb1`.
- The TV box lives on your normal LAN: `192.168.1.0/24`, pfSense at `192.168.1.1`.

**One tip up front:** don't put the box on a dedicated IPTV VLAN. It's tempting for isolation, and it's fine
for testing, but the TV+ box doubles as a Chromecast, and casting is far less painful when the box shares a
subnet with your phones and laptops. I run mine straight on my regular LAN.

---

### 1. Bring up VLAN 4

Create a VLAN with tag **4** on your WAN-facing physical port (`igb1` becomes `igb1.4`), then assign it as a new
interface. I called mine `WAN_IPTV`.

You don't need to configure this as a WAN-type interface. It effectively acts as one, since the classless route
KPN pushes over DHCP (step 2) sends the TV traffic out through it, but that happens on its own. You just assign
the interface and let DHCP do the rest.

Configure `WAN_IPTV`:

- **IPv4 type:** DHCP
- Under **DHCP client configuration -> Advanced configuration**, enable it and set:
  - **Send options:** `dhcp-class-identifier "IPTV_RG"`
  - **Request options:** `subnet-mask, routers, classless-routes`
- **Disable** *Block private/loopback/bogon networks* on this interface. KPN's TV network is all RFC1918-ish and
  bogon filtering will drop it.

The `IPTV_RG` class identifier is the magic word. KPN only hands out an IPTV lease to a client that identifies
itself as an IPTV residential gateway. This is DHCP **option 60**, sent *by the client*.

> **Use ISC DHCP, not KEA.** On current pfSense the KEA DHCP client does not work for this. Switch back to the
> deprecated ISC DHCP client or you'll never get a lease.

---

### 2. What the lease looks like

Once it's up, `WAN_IPTV` gets an address from KPN's TV range, e.g. `10.88.72.3/21`. Because you requested
`classless-routes` (option 121), it also gets a **classless static route** pointing at KPN's TV platform, e.g.:

```
213.75.112.0/21 via 10.88.72.1
```

pfSense installs that as a static route automatically. That `213.75.0.0/16` / `217.166.0.0/16` space is where
KPN's TV servers and the multicast sources live. Note there's **no default gateway and no DNS** on this
interface, and that's intentional. TV traffic only follows those specific routes.

---

### 3. Your LAN's DHCP server

Run your normal DHCP server on the LAN the box lives on, with one extra option:

- **Option 28 (broadcast-address):** `192.168.1.255`, the broadcast address of that LAN.

That's it. **Do not** set option 60 (`IPTV_RG`) on this DHCP *server*. One guide tells you to, and it's wrong:
option 60 is a *vendor class identifier the client sends*, not something a server hands out.

---

### 4. NAT outbound

Switch outbound NAT to **Hybrid** so you can add manual rules without losing the automatic ones. Add:

- **Interface `WAN_IPTV`**, source: your LAN subnet, destination: `*`, **static port: yes**.
  This NATs the TV box's traffic toward KPN's TV platform (the `213.75.112.0/21` route wants to leave via this
  interface and won't work without it).
- **Interface `WAN`**, source: your LAN subnet, destination: `*`.
  General rule so the box can reach the internet for its EPG/updates over your normal WAN.

---

### 5. Firewall rules

**On `WAN_IPTV`** (traffic coming in from KPN's TV network):

- Allow **IGMP**, source `*`, destination `224.0.0.0/4`, with **Allow IP options: yes**.
- Allow **UDP**, source `*`, destination `224.0.0.0/4`.

**On your LAN:**

- Allow **UDP** from the LAN to *This Firewall (self)* on port **53** (DNS).
- Allow **IGMP** from the LAN to `224.0.0.0/4`, with **Allow IP options: yes**.
- Allow the LAN out to `*` (normal WAN access; you presumably already have this).

> **Why "Allow IP options"?** IGMP membership reports carry the Router Alert IP option. pfSense drops packets
> with IP options by default, so IGMP silently dies unless you tick this on the IGMP rules. Make sure these
> rules sit *above* any generic match-all rule on the same interface.

---

### 6. IGMP proxy

Routed IPTV is multicast, and your LAN is a different segment from KPN's TV VLAN, so something has to forward
the multicast streams across. That's the IGMP proxy: it listens for the box joining a channel (an IGMP group)
and pulls that stream down from the upstream.

Enable **Services -> IGMP Proxy** and add:

- **Upstream:** interface `WAN_IPTV`, values `0.0.0.0/1, 128.0.0.0/1`. Two halves of the address space instead
  of `0.0.0.0/0`, because the proxy wants a non-zero mask and a literal `/0` didn't work for me. These two
  together cover the same range. The upstream is deliberately this wide because KPN originates its TV/multicast
  streams from several publicly routable IP ranges. Those ranges all belong to KPN, so you could scope this down
  to just their networks; I just haven't bothered.
- **Downstream:** interface `LAN`, value `192.168.1.0/24` (your LAN).

Finally, on **every switch** between pfSense and the TV box, enable **IGMP snooping** and **fast leave**.
Without snooping, multicast floods every port; without fast leave, channel switching is sluggish because old
streams take too long to stop.

At this point, plug in the box and it should pull a picture.

---

### A gotcha worth knowing about

`igmpproxy` on pfSense can wedge itself. If you repeatedly disable/enable the service, especially while the box
is actively streaming, the daemon can deadlock inside FreeBSD's multicast teardown and end up stuck in an
unkillable `D` state (even `kill -9` won't touch it). Once that happens, every new instance blocks on the same
lock and only a **reboot** clears it.

Symptoms: TV stops working, `netstat -g -f inet` shows the VIF table populated but the forwarding table empty,
and no IGMP queries going out on the downstream. If you see that, **stop poking it and reboot**. Don't keep
toggling the service, that's what makes it worse. If you want to stop it cleanly, turn the box off first, wait
for the streams to drain, *then* stop the proxy.

---

### Running the box on multiple networks

Got multiple LAN networks and want the box to work on all of them? Repeat the LAN-side steps for each subnet:

- DNS + WAN-access firewall rules.
- The IGMP allow rule with **Allow IP options: yes** (above any match-all rule).
- An outbound NAT rule on `WAN_IPTV` (source = that subnet, static port).
- A downstream entry in the IGMP proxy for that subnet.
- IGMP snooping + fast leave on the switches carrying that VLAN, same as before.

---

### Sources

- **KPN Internet: Specificaties voor modem/router op VDSL** (v1.2, Jan 2023). KPN's own spec, which documents
  the routed IPTV setup: VLAN 4 (802.1q) for TV, DHCP with option 60 = `IPTV_RG`, requesting options 1/3/28/121,
  no DNS/no default gateway, IGMPv2 proxy with fast-leave, routed mode (no bridge).
  [KPN document](https://kpn.com/w3/file?uuid=cd5f3398-4bad-4cdc-ac18-4f63361931b5&owner=8de86c61-bdc8-4848-872e-e5b380e682fc&contentid=50186&mode=incontext)
- **HellStorm666: KPN Routed IPTV with OPNsense.** A useful OPNsense walkthrough that got me most of the way.
  Heads up though: its IGMP-proxy upstream masks (`10.0.0.0/1, 217.166.0.0/1`) look off, and its DHCP option 60
  advice belongs on the client, not the LAN server (see step 3).
  [GitHub repo](https://github.com/HellStorm666/KPN-Routed-IPTV-with-OPNsense)
