# Homelab!

![](/assets/homelab-rack.png)
_Slightly outdated, doesn't include 10G switch and recent CPU upgrades._

From top to bottom, my homelab contains the following hardware:
- `sw-serverroompoe`: 16-port PoE switch (TP-Link TL-SG1016PE)
- 24-port keystone patch panel (cheapest I could find)
- `sw-serverroom`: 24-port switch (TP-Link TL-SG1024DE)
- `sw-serverroom10g`: 8-port 10 GbE switch (TP-Link TL-SX3008F)
- `sdr`: Raspberry Pi 5 with 2x RTL-SDR dongles attached
  - 4 GB RAM
  - Broadcom BCM2712 SoC 4-core ARM CPU @ 2.4 GHz
  - 64 GB SD-Card
- `truenas-backup`: Old PC with some disks for weekly offline backups of important pools
  - 6 GB RAM (?)
  - Old AMD A-series CPU (?)
  - 5x2 TB HDDs (Raid-Z1 redundant)
- `nebula`: Converted consumer server with 2 GPUs (running Proxmox)
  - 64 GB RAM
  - Intel Core i7 7700k 8-core CPU @ 4.2 GHz
  - 250 GB SSD (bootdisk), 2x1TB HDD (unused)
  - 2x NVIDIA GTX 1070 GPU
  - NVMe to 10G SFP+ adapter
- `blue-iris`: Dell R330 server (running Blue Iris)
  - 16 GB RAM
  - Intel Xeon E3-1230 v6 8-core CPU @ 3.5 GHz
  - 250 GB SSD (bootdisk), 2x2TB SAS HDD (NVR storage)
  - NVIDIA Tesla P4 GPU
- `phoenix`: Supermicro 1U server (running Proxmox)
  - 64 GB RAM
  - Intel Xeon E3-1230 v6 8-core CPU @ 3.5 GHz
  - 500 GB SSD (bootdisk)
  - Dual 10G SFP+
- `pfsense`: Supermicro 1U server (running pfsense)
  - 16 GB RAM
  - Intel Xeon E3-1270 v3 8-core CPU @ 3.5 GHz
  - 2x120 GB SSD (mirrored bootdisk)
  - Dual 10G SFP+
- `dragonnas`: Dell R720 server with 8x4TB HDDs as a NAS (running TrueNAS)
  - 96 GB RAM
  - Intel Xeon E5-2630L v2 12-core CPU @ 2.4 GHz
  - 1 TB SSD (bootdisk)
  - 8x4 TB SAS HDDs (bulk storage, Raid-Z2 redundant)
  - 2x2 TB NVMe SSDs (VM storage, mirror redundant)
  - Dual 10G SFP+ and dual 1G

Not locally, I have some additional VMs:
- `hetzner-docker-arm`: An ARM VPS running Docker stacks through portainer that need a fast internet connection, a publicly accessible IP or both.
- `redfire-vm`: A VM running on the Homelab cluster of a friend of line, mainly running [Uptime Kuma](https://uptime.kuma.pet/) to monitor my public services.

Using the 2 TB NVMe SSD storage (soon to be 4 TB) on `dragonnas`, both hypervisors `nabula` and `phoenix` store
all VM disks on the network attached storage device.

In total, including running some PoE devices like a couple Wi-Fi APs and cameras, the total power consumption hovers
around 400 watt, depending on what the cluster is doing.

# Self-hosted Services
Some notable services that are running on the cluster are...

## Blue Iris
Blue Iris is a really great NVR software, working with my ReoLink cameras. It really likes to have a GPU for
hardware en- and decoding if you have more than just a couple cameras. It's running on a legit
Windows Server 2016 license I found on a sticker on the R330 server!

## Home Assistant
Of course, any home that has a room dedicated to servers needs smart devices. And what better way to control these
devices than to control them all locally. I have a mix of Philips Hue lighting and switches, TP-Link Tapo energy
monitoring plugs and some custom ESP stuff that I can control with my home assistant instance. Most of the
communication also flows through MQTT, which allows me to create intuitive flows with Node-RED to, for example,
control my ESP-relay-controlled doorbell chime when my Ring camera tells me someone presses the doorbell button.

I wrote many of these "mqtt-bridge" services myself. They are mostly semi-quickly written Python scripts, but
they're written in such a way that it's easy to adapt to someone's custom environment and as stable as it can be.
You can find these bridges on my GitHub [here](https://github.com/search?q=owner%3AWouterGritter+mqtt-bridge&type=repositories)!

## Pterodactyl
Who doesn't love some gaming with friends? I have Pterodactyl running to be able to quickly make Minecraft servers
and the odd server for a random Steam game.

## PiHole
Goes without saying of course! I have PiHole running in two different VMs, on two different hypervisors for redundancy.
This has saved me a lot of times already when turning off a hypervisor, because I rely on my custom DNS to connect to them.

## LanCache and APT cache
I run [LanCache](https://lancache.net/) to cache Steam games and Windows updates, and [AptCacherNg](https://wiki.debian.org/AptCacherNg)
to cache APT packages. I don't have a very fast WAN connection, so this helps a lot when updating. Also, seeing the multi-gigabit
speeds when `apt upgrade`-ing and (re)downloading a game is awesome!

## Lots and LOTS of Docker stacks
The aforementioned services all run in VMs for various reasons, and I try to run most of my self-hosted services
in Docker when possible. I'll add more information about them on here later, but for now, here's an incomplete list
of my running stacks:
- immich
- grafana (+ prometheus/influxdb/telegraf)
- jellyfin & plex
- mosquitto
- various MQTT bridges, bridging APIs like the hue api to my MQTT broker
- nginx-proxy-manager for local HTTPS and a couple public services routed through Cloudflare tunnels
- node-red
- ollama
- docker registry
- uptime kuma
- some custom scripts for logging various things in Discord
