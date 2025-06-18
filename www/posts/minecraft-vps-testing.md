<!-- title = Minecraft VPS performace testing -->
<!-- longtitle = Minecraft server performance testing on VPSs -->
<!-- postdate = 18th of June 2025 -->
<!-- description = Testing the performance of Minecraft servers on various (cheap) VPSs and other hosts. -->

# @longtitle

_Posted on @postdate._

I've been messing around with testing Minecraft server performance on various VPS hosts (and a few non-VPS systems too),
mainly to see how they handle chunk generation. I'm using Spigot 1.21.5 with the
[Fast Chunk Pregenerator](https://www.spigotmc.org/resources/fast-chunk-pregenerator.74429/) plugin,
running the `openjdk-21-jre-headless` JVM.

The main reason I'm doing this is because I'm currently self-hosting a private survival Minecraft server,
and I'm trying to figure out if I should keep doing that or switch to a VPS host instead. My current setup is
fine for the most part, but the biggest limitation is network bandwidth—especially when a few people are online
at once. A cheap but performant VPS might be a better option if I can find one that handles chunk generation well.

I'm using chunk generation as a benchmark because it's usually the most demanding task a Minecraft server has to
deal with. In survival servers, especially later in the game when people are flying around in elytras at high speeds,
servers need to generate and send chunks constantly. If a system can handle that smoothly, it'll probably
handle everything else just fine.

### Setup
- **Server software:** Spigot 1.21.5
- **Plugin:** Fast Chunk Pregenerator
- **Command:** `fcp start 250 world`
- **RAM:** 6GB allocated to the Minecraft server (unless otherwise noted)

I used chunk generation speed (chunks per second) as the main benchmark.

## Results

### Hetzner VPSes (paid)
| Name      | CPU             | Cores | RAM (MC)  | Price (€/mo) | Chunks/sec | Cost efficiency <br> (Chunks/sec per €/mo) |
|-----------|-----------------|-------|-----------|--------------|------------|--------------------------------------------|
| **CPX31** | AMD (shared)    | 4     | 8GB (6GB) | €15.85       | **16.87**  | 1.06                                       |
| **CCX13** | AMD (dedicated) | 2     | 8GB (6GB) | €14.51       | 14.08      | 0.97                                       |
| **CAX21** | ARM64 (Ampere)  | 4     | 8GB (6GB) | €7.25        | 11.85      | **1.63**                                   |
| **CX32**  | Intel (shared)  | 4     | 8GB (6GB) | €7.62        | 6.68       | 0.88                                       |

### Other Systems (just for fun)
| Name             | CPU          | Cores | RAM (MC)   | Platform              | Chunks/sec |
|------------------|--------------|-------|------------|-----------------------|------------|
| **Dell R330**    | E3-1240 v5   | 4     | 8GB (6GB)  | Virtualized (Proxmox) | 13.40      |
| **Dell R730xd**  | E5-2697A v4  | 4     | 8GB (6GB)  | Virtualized (ESXI)    | 14.08      |
| **Asus Zenbook** | Ultra 9 185H | Many  | 32GB (6GB) | Bare metal (Windows)  | 14.51      |

## Notes
- **CPX31** is the top performer among Hetzner VPSes. Not bad for a shared-core VM.
- **CCX13** performs really well considering it only has 2 cores—but they're dedicated.
- **CAX21** (ARM) gives surprisingly solid results for its low price. Definitely good value.
- **CX32** is... not great. Maybe older CPUs or just less efficient in this context.
- I'm really surprised how well my [10 year old](https://www.intel.com/content/www/us/en/products/sku/88176/intel-xeon-processor-e31240-v5-8m-cache-3-50-ghz/specifications.html) (currently worth [20 euro](https://www.ebay.nl/sch/i.html?_nkw=Intel+Xeon+E3-1240+v5+cpu&_sacat=0&_from=R40&_trksid=m570.l1313&_odkw=E3-1240+v5+cpu&_osacat=0)) CPU is doing, compared to the VPS solutions out there.

---

## Final Thoughts
I did this out of curiosity, and it's fun to see how different setups stack up for Minecraft chunk generation. If you're running a server and want to pregenerate worlds quickly, this gives you a rough idea of what to expect across different hosts.

I’ll probably keep updating this as I test more systems. If you’re doing something similar, feel free to use the same format!
