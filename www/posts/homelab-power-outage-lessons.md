<!-- title = Homelab power outage lessons -->
<!-- longtitle = Lessons from a power outage: restoring my homelab and network -->
<!-- postdate = 7th of June 2025 -->
<!-- description = A small write-up about my findings after a short power outage; one that caused more down-time than necessary. -->

# @longtitle

_Posted on @postdate._

Recently, I ran into a frustrating power outage that knocked my entire homelab offline. While the downtime itself
wasn’t catastrophic, the process of getting everything back online made me painfully aware of some weak points
in my setup. In hindsight, it was a valuable experience that forced me to rethink parts of my infrastructure.

Here’s a breakdown of what went wrong, how I dealt with it, and a few key takeaways that might help others
running similar setups avoid the same headaches.


### What Happened
After power was restored, I expected most of my equipment to boot back up and start functioning more or less normally. That didn’t happen.

#### The NAS Didn’t Auto-Boot
My NAS, which hosts both HDD and SSD storage and backs all my VM disks, didn’t turn itself back on after the power
outage. This turned out to be a BIOS setting—auto-boot after power loss was simply disabled.

This had a cascading effect: since the VM disks live on the NAS, nothing that depends on them could start properly until it came back online.

#### Internet was up, but DNS was down

TODO: Jeff Geetling IT WAS DNS

The network came back up, and I had internet access. However, internal and external DNS resolution was completely broken. This caused problems like:
- Not being able to reach either of my Proxmox hypervisors by hostname.
- Pi-hole, which handles DNS for the network, wasn’t running since it’s a VM hosted on Proxmox.
- I rely on DNS to access many internal tools and services, including password management and remote access.

One ironic twist: to start the Pi-hole VM, I needed to get into the Proxmox interface, but I couldn’t resolve its
hostname due to DNS being down. A real chicken-and-egg situation.

Thankfully, I could still reach my router, and used `nslookup` against its IP to resolve the Proxmox host's IP address.
Luckily I have all my internal DNS entries configured in pfsense, which Pi-hole normally uses to forward my internal TLD to.

#### Proxmox quorum issues
On both of the Proxmox nodes, the VMs wouldn’t start. It turned out to be a cluster quorum issue; I had recently decommissioned a third node
without removing it from the Proxmox cluster. Because the node could never be reached, the cluster refused to start any VMs.

Short-term fix: I manually ran `pvecm expected 2` to force quorum. The catch? I had to remember that command without internet access.
Luckily, I found it in `.bash_history` using `grep` because I had had this issue before.

After this I implemented a more long-term fix, namely removing the offline node from the Proxmox cluster.

#### Boot errors on the second Proxmox node
On rebooting the second Proxmox host, I ran into a cryptic `Cannot initialize CMAP service error.` I’m still not
exactly sure what caused it, but a reboot cleared it up. Possibly a timing or corruption issue during the power loss.


---

### Key Takeaways
This experience gave me a solid list of things I need to change and improve. If you’re running a homelab or any
sort of self-hosted infrastructure, some of these might be worth acting on:

#### 1. Enable auto-boot on all critical devices
It’s easy to forget if you don't get many power outages, but make sure your NAS and servers are set to auto-boot
after a power failure. One unchecked BIOS setting can take your whole stack down longer than necessary.

#### 2. Redundant or backup DNS is not optional
Relying solely on a DNS VM hosted inside your infrastructure creates a fragile loop.
Consider having a secondary DNS source available—whether it’s your router, a little Raspberry Pi with
its sole purpose being DNS or forcing critical (static!) IPs in your `/etc/hosts`.

#### 3. Document critical IP addresses
When DNS fails, knowing IP addresses of key infrastructure (hypervisors, NAS, router, management UIs) can save you.
Write them down somewhere physically accessible, or keep a copy on an offline device like your local drive (not your NAS!).

#### 4. Password access shouldn’t be internet-dependent
If your password manager only works with a cloud sync or DNS resolution, that’s a problem.
Make sure you have offline access to key credentials or at least a secure backup (even an encrypted text file locally can do the job in a pinch).

#### 5. Simplify your proxmox cluster
If you don’t consistently run multiple nodes, consider whether you need a cluster at all.
Clustering adds complexity—like quorum requirements—that can bite you during partial outages.
Sometimes, standalone nodes are more resilient for a small-scale setup.


### Final Thoughts
Power outages are inevitable, but cascading failures don’t have to be. I thought I had a fairly robust setup, but this
incident exposed several weak points I hadn’t fully considered. It was a good reminder that automation, documentation,
and a bit of simplicity can go a long way in making recovery faster and less stressful.

If you’re running your own infrastructure at home or for a small team, take a few minutes to audit your boot settings,
DNS setup, and cluster configuration. Hopefully, your next recovery process will be a bit smoother than mine was.
