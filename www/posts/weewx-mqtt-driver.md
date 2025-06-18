<!-- title = WeeWX MQTT driver -->
<!-- longtitle = Collecting weather data from MQTT in WeeWX using a custom driver -->
<!-- postdate = 26th of January 2025 -->
<!-- description = Testing the performance of Minecraft servers on various (cheap) VPSs and other hosts. -->

# @longtitle

_Posted on @postdate._

I’ve been running a small weather station setup at home and using WeeWX to visualize the data.
If you haven’t heard of [WeeWX](https://www.weewx.com/), it’s an open-source weather software that takes in data from various weather
stations and displays it in a slick dashboard you can view in your browser. It’s written in Python and pretty
easy to extend — which turned out to be a lifesaver for my use case.


### The problem
My weather station doesn’t output data in a way WeeWX natively understands. Instead, I’ve got a little custom setup
that listens to 433 MHz signals (you could also do 868 or 915 MHz) using a Software Defined Radio (SDR) dongle.
Then, with a custom script I wrote — [433-mqtt-bridge](https://github.com/WouterGritter/433-mqtt-bridge) — I publish
that data to an MQTT broker.

The idea is that MQTT acts as the hub: the data comes in from the SDR, gets interpreted and published to MQTT topics,
and then can be consumed by whatever I want — like Home Assistant, Grafana, or in this case, WeeWX.

But out of the box, WeeWX doesn't support pulling data from MQTT. So, I wrote a driver for it.


### My Custom MQTT Driver for WeeWX
I put together a custom Python driver for WeeWX that lets it subscribe to MQTT topics and treat the incoming
values like sensor readings. Here's the driver on GitHub Gist:

👉 [View the driver code](https://gist.github.com/WouterGritter/4f3a0c598323df536dcf9e8807c8d7a6)

It's super straightforward, and it integrates nicely with my existing MQTT setup. It reads the most recent values
from specified topics, does some basic conversion, and then passes that into WeeWX like any other
station hardware driver would.


### What It Supports
This driver expects metric values and supports a range of weather parameters:

- Temperature
- Humidity
- Wind speed & gusts
- Wind direction
- Rain & rain rate
- Pressure
- UV
- Light

If you want to convert values (like from °F to °C or mph to km/h), you can tweak the multiplier section in the
driver (check around line 70 in the code).

### How to Install the MQTT Driver
1. **Download the driver file** <br>
   Grab the `.py` file from the Gist link and drop it into your `weewx/drivers` directory. On a typical Linux install, this is:
   ```bash
   /usr/share/weewx/weewx/drivers/
   ```
2. **Install the MQTT Python package** <br>
   The driver uses the `paho-mqtt` library to subscribe to MQTT topics, so make sure it’s installed:
   ```bash
   pip install paho-mqtt
   ```
3. **Update your WeeWX config** <br>
   In your `weewx.conf` file (typically found at `/etc/weewx/weewx.conf`), update the `[Station]` section and add a new `[Mqtt]` section. Here’s an example:
   ```ini
   [Station]
      # ... your other configuration
      station_type = Mqtt
   
   [Mqtt]
      mqtt_broker_host = localhost
      mqtt_broker_port = 1883
      temperature_topic = weatherstation/temperature
      humidity_topic = weatherstation/humidity
      gust_speed_topic = weatherstation/gustspeed
      wind_speed_topic = weatherstation/windspeed
      wind_direction_topic = weatherstation/winddirection
      uv_topic = weatherstation/uv
      light_topic = weatherstation/light
      rain_topic = weatherstation/rain
      rain_rate_topic = weatherstation/rain_rate
      pressure_topic = weatherstation/pressure
      hardware_name = My Weather Station Hardware
      driver = weewx.drivers.mqtt
   ```
   Of course, update the topic names if you're using something different in your MQTT setup.
4. **Restart WeeWX** <br>
   After making all the changes, just restart the WeeWX service and it should start pulling in data from your MQTT broker.
   ```bash
   sudo systemctl restart weewx
   ```


### Why This Is Cool
What I like about this setup is that it completely decouples the weather station hardware from the visualization layer.
I can use the same data stream in WeeWX, Home Assistant, and even feed it into a time-series database like
InfluxDB for custom dashboards in Grafana.

This also makes it way easier to support random off-brand or legacy weather stations that don’t have native
WeeWX support — as long as you can decode the signal and push it to MQTT, this driver should work.


### Gotchas & Customizing
- The driver expects metric units. If your MQTT data is in imperial or something else, you’ll need to adjust the conversion factors in the code.
- It's a simple poll-every-X-seconds kind of model (based on WeeWX’s usual loop), not an event-driven thing. That said, it’s plenty responsive for most weather data needs.
- You might need to fiddle with your `loop_interval` or observation timestamps if you’re seeing weird gaps or timing issues.

