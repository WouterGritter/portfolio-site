<!-- title = Hosting BlueMap using Docker -->
<!-- longtitle = Offloading BlueMap hosting to a VPS using docker and SQL -->
<!-- postdate = 24th of June 2025 -->
<!-- description = A technical and informative article on how I created a Docker stack for running a BlueMap webserver external from a Minecraft server. -->

# @longtitle

_Posted on @postdate._

If you're into hosting your own Minecraft server, you've probably come across [BlueMap](https://github.com/BlueMap-Minecraft/BlueMap).
It's an awesome tool that renders your Minecraft worlds into 3D, interactive web maps that players can explore from any browser.

But here's the thing: serving those maps can be bandwidth-heavy. I ran into this exact issue in my own homelab setup.
My Minecraft server runs just fine on my home network — but my upload bandwidth is limited to around 10 Mbit/s.
While that's more than enough for Minecraft gameplay, it's painfully slow when it comes to hosting the BlueMap webserver,
which serves a lot of image tiles and binary blobs.

So, I built a solution: a Docker stack that offloads the BlueMap webserver and tile storage to a remote VPS, using a shared
SQL backend to sync everything. That means the Minecraft server at home just updates the SQL database — and the VPS
serves everything else, fast and smooth.


### What's BlueMap, anyway?

BlueMap is a powerful map rendering tool for Minecraft. It converts your worlds into an interactive 3D map that you can
explore from your browser. Think of it like Google Maps, but for your Minecraft server.

It supports real-time updates, live player tracking, and can be run as a plugin or standalone renderer. Under the hood,
BlueMap stores rendered tile data either as flat files (on disk) or inside a SQL database (MariaDB/MySQL), depending on
how you configure it.


### The problem: bandwidth bottleneck

My Minecraft server runs on my homelab machine. I’ve got enough CPU and RAM to keep it running smoothly, but my
home upload speed (~10 Mbit/s) just can’t keep up with the load that BlueMap’s webserver demands.

Every time someone zooms or pans around on the map, the browser pulls dozens of image tiles — and when several people
are using it at once, it quickly becomes a problem.

I wanted the best of both worlds:
- Let my home server render and update the map data.
- Host the web interface from a VPS with much better bandwidth and uptime.


### The solution: SQL-backed BlueMap on a remote VPS

BlueMap supports using SQL as a backend for storing map tiles. That’s the key. Instead of rendering to disk and
serving files locally, you can write rendered data to a MariaDB/MySQL database.

Then, you can run the BlueMap webserver separately — even on a different machine entirely — and have it read from
that same SQL database.

Here's what that setup looks like in practice:
- Home server: Renders maps with BlueMap, writes data to remote SQL database.
- VPS: Runs BlueMap in webserver mode only, reading the data from SQL and serving it with high-speed bandwidth.


### The Docker stack

To make this setup easy to deploy, I wrote a small [Docker-based wrapper for the BlueMap standalone webserver](https://github.com/WouterGritter/bluemap-webserver-docker).
It runs in a container, connects to the SQL backend, and serves the map UI.

You can get it running with Docker Compose in just a few steps.

---


### How to set it up

Here's how to deploy this setup on your own setup.


#### 1. Clone the Docker stack

Start by cloning the repository:

```bash
git clone https://github.com/WouterGritter/bluemap-webserver-docker
cd bluemap-webserver-docker
```


#### 2. Configure the Docker compose stack

Copy the `.env.example` file to `.env`:
```text
DB_ROOT_PASSWORD=<randomly generated password>
BLUEMAP_PROXY_TARGET=192.168.1.100:8100
```

The `BLUEMAP_PROXY_TARGET` is used to proxy requests for live player data and UI settings from your Minecraft server.
This helps keep everything working as if it were one machine.

Then spin up the stack:
```bash
docker compose up -d
```

This runs:
- A MariaDB server
- A NGINX server running BlueMap, configured to proxy live data


#### 3. Make the SQL DB reachable from your minecraft server

You need to make sure your Minecraft server can connect to the MariaDB server running in this stack.

Two options here:
- Expose MariaDB publicly with proper firewall rules (not really recommended).
- Better: set up a private VPN using [WireGuard](https://www.wireguard.com/) or [Tailscale](https://tailscale.com/).
  This keeps traffic secure and avoids exposing your DB to the internet.

Once connected, your home server will treat the remote DB like it's local.

#### 4. Configure BlueMap on your minecraft server

Update your BlueMap config to use SQL for tile storage. This can be done in the following files:
- `bluemap/storage/sql.conf`
- `bluemap/maps/*.conf`

Follow the instructions on how to configure a SQL storage backend in the comments.

After restarting BlueMap, it will start rendering tiles and uploading them to the SQL server instead of writing to local disk.


#### 5. Done!

You should now be able to visit the BlueMap web interface running on your VPS.
Tiles should load instantly, after they're generated of course, and your home upload won't be saturated.


### Final thoughts

This setup has been running rock-solid for me. It offloads the heavy lifting from my limited home network while keeping the render job close to the Minecraft server itself.

If you’re running Minecraft from your homelab, but want a snappy, always-online map UI — this is a solid way to do it.

Got questions, improvements, or ideas? Check out the [repo](https://github.com/WouterGritter/bluemap-webserver-docker/blob/master/.env.example) and feel free to contribute.
