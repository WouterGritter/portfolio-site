<!-- title = ESP8266 lamp project -->
<!-- longtitle = ESP8266 lamp project -->
<!-- postdate = 29th of January 2019 -->
<!-- description = My journey to build a completely DIY and custom IoT relay to control lights. -->

# @longtitle

_Posted on @postdate._

Some time ago my dad and I made a couple of WiFi controlled lamps, with the help of the ESP8266 micro-controller.
It's a completely DIY project, and I wrote my own software for both the ESP's, and the control server is running on
our linux machine. In total we made three, but I'd really like to make a couple more.

We bought all the parts from Banggood, and for those three controllable lamps we spent about 37 euros in total.
This means each unit costs a little bit over 12 euros, which is not too bad in my opinion.
Especially because it has a sweet see through plastic cover on the front, so you can see each little LED blinking away.

Full parts list:
- Waterproof plastic box to house the electronics ([link](https://www.banggood.com/3Pcs-SONOFF-IP66-Waterproof-Junction-Case-Waterproof-Box-Water-resistant-Shell-p-1279762.html?rmmds=myorder&cur_warehouse=CN))
- Isolated mains to 5v dc power supply ([link](https://www.banggood.com/3Pcs-AC-DC-Isolated-AC-110V-220V-To-DC-5V-600mA-Constant-Voltage-Switch-Power-Supply-Converter-p-1144707.html?rmmds=myorder&cur_warehouse=CN))
- ESP8266 to connect to the WiFi and execute the software ([link](https://www.banggood.com/3Pcs-Upgraded-Version-1M-Flash-ESP-01-WIFI-Transceiver-Wireless-Module-p-980109.html?rmmds=myorder&cur_warehouse=CN))
- A single channel relay board to switch the power going to the lamp ([link](https://www.banggood.com/3Pcs-5V-Relay-5-12V-TTL-Signal-1-Channel-Module-High-Level-Expansion-Board-For-Arduino-p-1178211.html?rmmds=myorder&cur_warehouse=CN))
- 3.3v regulator to power the esp ([link](https://www.banggood.com/5V-To-3_3V-DC-DC-Step-Down-Power-Supply-Buck-Module-AMS1117-800MA-p-933674.html?rmmds=myorder&cur_warehouse=CN))
- Some jumper wires such that we can reprogram the esp later ([link](https://www.banggood.com/120pcs-20cm-Male-To-Female-Female-To-Female-Male-To-Male-Color-Breadboard-Jumper-Cable-Dupont-Wire-Combination-For-Arduino-p-974006.html?rmmds=myorder&cur_warehouse=CN))
- And finally a USB to serial adapter to program the esp ([link](https://www.banggood.com/USB-To-ESP8266-WIFI-Module-Adapter-Board-Mobile-Computer-Wireless-Communication-MCU-p-1224390.html?rmmds=myorder&cur_warehouse=CN))

The first step of the project was prototyping. After hot gluing all parts together it looked something like this:
![](/assets/esp8266-lamp-wip-1.jpg)

And after nervously connecting mains power to it, it worked without any sparks!

![](/assets/esp8266-lamp-wip-2.jpg)

At this stage the relay could be controlled by the website front-end I had developed and tested beforehand.
Now it was a matter of putting everything in a small plastic and waterproof box, and making 2 more of them.
Of course I found better ways of making and connecting everything as I went.

All together in the box it looked like this:
![](/assets/esp8266-lamp-finished-box.jpg)

And screwed in place:
![](/assets/esp8266-lamp-finished-wall.jpg)

For “safety” reasons we added an ordinary switch in series with the live wire going to the esp, and through the esp to the lamp.
We did this because if anything went wrong we could just switch off power to the thing.
One side-effect from adding the switch in series is being able to switch off the lamp when you're there without a phone.
Because when the esp starts up it defaults in the off state, you can power-cycle the esp to switch off the lamp.
Maybe it would've been nice to also be able to switch the lamp on using this method, but it would probably need
some extra hardware, such as a capacitor and a resistor to remember the state of the lamp for the
couple of seconds the esp didn't have power.
