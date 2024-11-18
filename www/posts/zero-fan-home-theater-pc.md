# Zero-fan, GTX 1060 powered Home Theater PC

<!-- title = Zero-fan Home Theater PC -->

_Posted on 9th of August 2020._

![](/assets/home-theater-desktop.jpg)


## The idea

We have always had a Home Theater PC (HTPC) in our living room. It was a makeshift computer, being made of old computer
parts and a cheap SSD. It worked fine, however there were a few compromises we had to make while using it.
The fans were very loud, startup times were slow (even though it had an SSD) and it lacked overall performance.
It could be used to stream Netflix or live television, sure, and it could even handle games like rocket league on low settings.
But it was still time for an upgrade!

We managed to get our hands on a decent gaming machine. It consists of a GTX 1060 graphics card and an Intel Core i5 processor.
Triple A games on medium settings are no problem for this machine. So, two of the three problems are solved: it's capable of
playing games with decent quality and the boot time is a lot faster. It was time to tackle the third problem: the noise...


## The execution

I've always been a fan of [Parsec](https://parsecgaming.com/). It's a piece of software that lets you stream your entire desktop seamlessly over the internet.
It's aimed mostly at the gaming scene, so you can connect to your powerful gaming tower from a low end device, but it works just
fine for general purpose desktop streaming. It supports streaming over the internet, but it works even better when using it in
your own (wired) LAN, which adds only one or two frames of latency.  After some digging online, it turns out you can run the
Parsec client software on a Raspberry Pi 3! Can you see where I'm going with this?

Luckily I had an old Raspberry Pi laying around in my shed, however it was very rusty. I crossed my fingers and plugged it in to
a 5v power supply and a monitor. It turns out a little rust on a Raspberry Pi isn't enough to kill the little thing!  
After reinstalling Raspbian (now known as Raspberry Pi OS) to the SD card and installing Parsec, the software worked flawlessly.
Connecting to the host PC did not have any issues at all, and the Pi could handle resolutions of up to 1080p with 60fps without any issues.
With both devices wired to our cat 6 equipped local network, there was virtually no input lag.

![](/assets/home-theater-raspberry-pi.jpg)

So the noise problem was tackled! After writing a few scripts to easily start up the Parsec client GUI and to connect to
the host automagically, it feels like you're using a windows machine running directly off of the Pi!  
Another benefit of using a Pi is that it can run 24/7 without using any noticeable amount of power.
Knowing this I have installed [Raspotify](https://github.com/dtcooper/raspotify): _A Spotify Connect client for the Raspberry Pi that Just Works™_.
With this it's possible to easily stream music to our 18 year old amplifier from any smartphone!


## Power savings!

So, the solution is awesome right! Having a computer you can always connect to using a fanless Raspberry Pi? Well, not quite.
The PC running Windows consumes about 60 watts when on idle. To solve this, I enabled Wake-on-LAN (WOL) on the Windows PC.
With this enabled, sending a special crafted broadcast packet to our network will turn on the PC as if it's magic.
I installed a program called etherwake on the Pi, following [this guide](https://www.cyberciti.biz/tips/linux-send-wake-on-lan-wol-magic-packets.html). With it I can start the PC remotely with a simple bash script.
But you know me: this wasn't enough.

The drawer the Pi is sitting in used to be the housing of the old Windows PC, and contained a make-shift power button.
It's basically a simple push button wired to the motherboard. The Pi has a lot of GPIO pins that can be used for LEDs and buttons,
so this is exactly what I did. It was just as simple as wiring the button to the Pi and writing a quick and dirty Python script
to execute a bash script when the button gets pressed.

![](/assets/home-theater-button-wires-closeup.jpg)

![](/assets/home-theater-button-wires.jpg)

Lastly I used Windows' Task Scheduler tool to shutdown the PC every night at 3 AM if it wasn't turned off already.
This prevents accidentally leaving the computer on and still wasting a lot of power.
