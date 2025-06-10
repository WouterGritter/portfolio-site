<!-- title = CS:GO Bomb Beep Pattern -->
<!-- longtitle = How I got the CS:GO bomb beep pattern -->
<!-- postdate = 21th of July 2020 -->
<!-- description = A write-up on how I generated a formula that fits the CS:GO bomb beeping pattern. -->

# @longtitle

_Posted on @postdate._

## The formula

In short, here is the formula for the beep pattern of the CS:GO bomb:  
![](https://quicklatex.com/cache3/47/ql_41ff49a536629603d822f8672fc0a147_l3.png)

Where _f(t)_ gives the BPS (Beeps Per Second), and _t (0 ≤ t ≤ 45)_ is the time in seconds (the in-game bomb
explodes after 45 seconds).

For a more generalized formula:  
![](https://quicklatex.com/cache3/04/ql_d31b46eded2266fa0a29f7f334770904_l3.png)

Where _g(t)_ gives the BPS (Beeps Per Second), and _p (0.0 ≤ p ≤ 1.0)_ is the percentage of the time that has
passed since the bomb has been armed. This is more useful when you don't want to be stuck using the 45 second
explosion time frame.

![](/assets/csgo-bomb-beep-pattern-graph.png)
_A plot of time v.s. beeps per second_

The length of each beep is fixed at 125ms per beep. These formulas approximate the beep pattern of CS:GO's bomb very accurately.

For information about the note(s) of the bomb beeping, I suggest taking a look at [this reddit post](https://www.reddit.com/r/GlobalOffensive/comments/9j7zvm/bomb_timer_tick_note/e6pdv3j/).
Apparently the notes are different depending on if the bomb is planted on bombsite A or B, fascinating stuff!


## How I got the formula

The interval between the beeps are very clearly exponential, so I needed to find or generate a matching exponential formula.
After some digging online I couldn't find any. At first, I tried estimating the beeps per second from a YouTube video of
the bomb sound roughly every 5-10 seconds, and filling this data into an Excel table. When I got this data,
I tried to let excel guesstimate the (exponential) formula for it. This was without any luck; the beep pattern still
seemed a bit off. Looking back at it, it was pretty obvious seeing as I was just loosely guessing the beeps per second
of the original sound.

At this time I had the choice to go all-in and find the exact formula or to make do with the relatively bad
formula I had figured out. Of course I chose to get the exact formula!

So, it was time to download an mp3 file of the bomb sound of the game. Luckily I found one pretty easily on YouTube,
even without any background music. I imported the file into Audacity and started to find the exact beeps per second
for some timestamps, roughly 5 seconds apart and 2 seconds apart when the bomb was about to explode. To find the
beeps per second, I selected the part between the start of a beep and the start of the beep after that one.
Audacity told me the length of the selection in milliseconds which is accurate enough for what I'm trying to do.
Dividing 1 by the length of the selection in seconds gave me the bps at that time.

After gathering 17 data points, it was time to figure out an exponential equation that fits the given data.
After using a really old-school application called [CADRE Regression](http://www.cadreanalytic.com/Regress.htm), it figured out the values for A0, A1 and A2
for a second degree exponential function:

![](/assets/csgo-bomb-regression.png)

I used these values in my in-real-life CS:GO (fake!) bomb project to match the in-game beep pattern.

![](/assets/csgo-bomb-arduino-beep-code.png)
_Code snippet of the CS:GO bomb project which calculates beep time between beeps. Note that the A1 and A2
values are different because I adjusted the formula to accept time t in milliseconds instead of seconds._
