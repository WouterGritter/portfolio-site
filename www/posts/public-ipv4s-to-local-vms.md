<!-- title = Routing public IPv4s to local VMs -->
<!-- longtitle = Routing public IPv4s to local VMs, through a VPS & pfSense with WireGuard -->
<!-- postdate = 17th of September 2025 -->
<!-- description = A guide on grabbing cheap IPv4's from VPS providers, and routing them through pfsense directly to your VMs. No 1:1 NAT. -->

# @longtitle

_Posted on @postdate._

I wanted to assign real, routable IPv4 addresses to hosts behind my pfSense firewall at home. The catch? I'm behind CGNAT,
so I can't just port-forward or announce my own ranges. Instead, I used a cheap VPS as a router and passed secondary IPv4s
over WireGuard into my homelab. The result: my VMs get public IPs, just like they were on the VPS directly.

This post is a rough walkthrough of what I did - not a professional guide, but enough that someone else could follow along.

---

### Why even bother?
- I wanted real public IPv4s on my internal VMs (mainly for Minecraft server hosting).
- I'm stuck behind CGNAT, so I can't get inbound connections otherwise.
- I didn't want to run everything directly on the VPS - I wanted to leverage the compute I have locally.

With this, traffic for my secondary IPs hits the VPS first, then gets shoved over WireGuard into pfSense. pfSense "owns"
those IPs and can hand them off to internal hosts. These hosts will see the public /32 IP as their own; it's not even
behind a 1:1 NAT.

---

### Latency considerations

You're essentially hairpinning your traffic through a VPS, so pick one as close as possible.

Example numbers from my setup, after trying some local VPS providers:
- I have around 7-8ms ping to my ISP's gateway
- This means 11ms ping to `1.1.1.1` directly
- My VPS has 11ms latency from home and only 2ms latency to `1.1.1.1`
- Traceroutes are also really short

The end result for my setup: 14-15ms ping to `1.1.1.1` after inserting the VPS into the path.
That's pretty acceptable for me.

### IP plan I used

Converted to [RFC5735](https://datatracker.ietf.org/doc/html/rfc5735) addresses, persistent throughout this tutorial:
- `10.99.0.0/30` -> WireGuard tunnel network
- `10.99.0.1` -> VPS WireGuard side
- `10.99.0.2` -> pfSense WireGuard side
- `203.0.113.10` -> VPS primary IP
- `203.0.113.55` -> VPS secondary IP (will end up on a VM behind pfSense)

---

### VPS configuration

The VPS has pretty much a normal WireGuard setup. My `/etc/wireguard/wg0.conf` looked like this:
```ini
[Interface]
PrivateKey = ...
Address = 10.99.0.1/32
ListenPort = 51820

[Peer]
PublicKey = ...
AllowedIPs = 10.99.0.2/32, 203.0.113.55/32
```
Note that `203.0.113.55/32` (the IP(s) that you want to pass to internal hosts) is included in `AllowedIPs`.

Enable + start WireGuard:
```bash
systemctl enable wg-quick@wg0
systemctl start wg-quick@wg0
```

#### Enable forwarding + tweak kernel settings
Create `/etc/sysctl.d/99-wg-router.conf`:
```conf
# Forwarding
net.ipv4.ip_forward=1

# Reverse-path filtering: loose mode
net.ipv4.conf.all.rp_filter=2
net.ipv4.conf.default.rp_filter=2
net.ipv4.conf.eth0.rp_filter=2
net.ipv4.conf.wg0.rp_filter=2
```

#### Proxy ARP for secondary IPs
My provider gave me a `/24` with `203.0.113.10` as primary, plus `203.0.113.55` as a secondary. Their router expects to
see ARP replies for `.55`. So I had to enable `proxy_arp` on my VPS:
```conf
# We're proxy-ARPing on an on-link /24
net.ipv4.conf.eth0.proxy_arp=1
```
In the same file.

Apply everything:
```bash
sysctl --system
```

---

### pfSense configuration

On the pfSense side, create the WireGuard tunnel:
- Address: `10.99.0.2/30`

Configure the WireGuard peer:
- AllowedIPs: `0.0.0.0/0` (important - you want all traffic destined for your secondary IPs to pass through)

Create a WireGuard interface:
- Static IPv4: `10.99.0.2/30`
- MTU: `1420`

Set up a gateway:
- Address: `10.99.0.1`
- Call it something like `vwan_gateway`
  Back in the interface settings, mark that gateway as the IPv4 upstream.

#### Quick test
Add the public IP (`203.0.113.55/32`) as a **virtual IP** on the WireGuard interface. Allow ICMP to it.

If everything's right:
- You can ping `203.0.113.55` from outside.
- pfSense replies.
- Latency should match what you expect (VPS + tunnel overhead).

If not, debug from the VPS with:
```bash
tcpdump -ni eth0 'icmp and host 203.0.113.55'
tcpdump -ni wg0 'icmp and host 203.0.113.55'
```

---

### Assigning the public IP to a VM behind pfSense

Now let's put that secondary IP on an internal host.

#### On the VM

Check its existing LAN config:
```bash
ip addr
ip route
```

Temporarily assign the public IP:
```bash
sudo ip addr add 203.0.113.55/32 dev ens18

sudo ip route del default
sudo ip route add default via <original LAN gateway> dev ens18 src 203.0.113.55
```

Or make it permanent with netplan:
```yaml
network:
  version: 2
  ethernets:
    ens18:
      dhcp4: false
      addresses:
        - <original LAN address>/24
        - 203.0.113.55/32
      routes:
        - to: 0.0.0.0/0
          via: <original LAN gateway>
          from: 203.0.113.55
```

#### Back in pfSense

Add a gateway (e.g. `vwan_internal_55`):
- Gateway address = the host's original LAN IP

Create a static route:
- `203.0.113.55` → `vwan_internal_55`

Add a LAN rule:
- Match source `203.0.113.55`, destination `*` (or any stricter WAN rule you might have)
- Advanced settings: gateway = `vwan_gateway`
- Make sure this rule is above your general LAN rules.

Note: Once your VM is using the public IP, it no longer matches your "LAN subnet" rules. If you have existing allow rules,
you'll need to duplicate them to also cover `203.0.113.55`. At this point there will also be no firewall to block
anything going to `203.0.113.55` - it'll go straight to your VM.

---

### Closing notes

That's it - now you’ve got a host behind pfSense using a VPS's secondary public IPv4 like it was directly on the internet.
This works even behind CGNAT.

But keep in mind, you don't get a firewall for free anymore. Traffic comes straight to your host. You'll need to manage
this yourself - either in pfSense or on the VM (UFW, nftables, iptables, etc.).
