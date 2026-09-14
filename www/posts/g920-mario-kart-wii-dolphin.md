<!-- title = G920 + Mario Kart Wii -->
<!-- longtitle = Getting a Logitech G920 working with Mario Kart Wii on Dolphin -->
<!-- postdate = 14th of September 2026 -->
<!-- description = A Dolphin GameCube-pad profile that maps a G920 wheel, pedals and paddle flippers to Mario Kart Wii, including a trick input that mimics pulling the real Wii wheel. -->

# @longtitle

_Posted on @postdate._

I've got a Logitech G920 hooked up to my racing chair, and wanted to play Mario Kart Wii with it through Dolphin.
Dolphin sees the G920 as a generic GameCube pad device, so it's just a matter of writing the right button/axis
mapping. I put some effort into making it feel like an actual Wii wheel rather than a GameCube controller
you happen to be steering with a wheel, so I figured I'd share the profile.

![Logitech G920 wheel](/assets/g920-mario-kart.jpg)

### Where to put it

Drop the file below in:

```
%appdata%\Dolphin Emulator\Config\Profiles\GCPad\
```

Then in Dolphin's controller settings, set Player 1 to a standard GameCube controller, pick the G920 as the
device, and load this profile. Mario Kart Wii runs fine on a GameCube controller, so no Wiimote emulation is
needed.

```ini
gist:https://gist.github.com/WouterGritter/d38bf3c41b87fa33dfd5b2d7d8f699cc
```
_Find the Gist [on GitHub](https://gist.github.com/WouterGritter/d38bf3c41b87fa33dfd5b2d7d8f699cc)_.

One more thing that matters a lot: turn the wheel's rotation range down. In Logitech G HUB, go to `G920` >
`Operating Range (Angle)` and set it as low as it'll go, 180 degrees on mine. Mario Kart Wii doesn't expect
you to turn a full lap of wheel rotation to hit a hard left or right, so leaving it at the default (900 degrees)
makes the steering feel numb and unresponsive. At 180 degrees it matches the game's steering much better.

### The mapping

| G920 input | Action |
|---|---|
| Wheel rotation | Steer left/right |
| Gas pedal | Accelerate (A), while racing |
| Brake pedal | Brake/reverse (B), while racing |
| `A` / `B` buttons | Confirm/back (A/B), in menus |
| `X` / `Y` buttons | X / Y |
| Start button | Pause menu |
| Wheel's hat switch | D-pad, for menu navigation |
| Left paddle flipper | Left trigger (drift) |
| Right paddle flipper | Right trigger (item) |
| Both paddle flippers | Trick/stunt (D-pad up) |

On a GameCube controller, A and B double as both the gas/brake and the menu confirm/back buttons, so the
profile ORs the pedals with the G920's A/B buttons on the same GameCube A/B input. Use the pedals to
accelerate/brake while racing, and the A/B buttons to navigate menus. Nothing stops you from using them the
other way around, but it's cumbersome and not what they're there for.

The last one is the part I actually care about. On a real Wii wheel, you do a trick off a ramp or a bike
wheelie by yanking the whole wheel up towards you. A wheel peripheral obviously can't detect that, so I used
the two paddle flippers together as a stand-in: pull both of them at once and it fires D-pad up. It's a
surprisingly good approximation of the real motion, and it means the L/R triggers stay free for drifting and
items like normal.

### Why the trigger logic looks weird

The tricky part is that the trick uses the *same two buttons* as the L and R triggers. When you physically
squeeze both flippers at once, they never register in the same instant, one always lands a few milliseconds
before the other. Without any extra logic, that gap fires a real L or R trigger press right before the trick
input completes, so every trick attempt also throws your item or starts a drift for a split second.

The `Triggers/L` and `Triggers/R` lines fix this with `pulse()`:

```ini
Triggers/L = `Button 5`&!pulse(`Button 5`,0.06)&!`Button 4`&!pulse(!`Button 4`,0.1)
Triggers/R = `Button 4`&!pulse(`Button 4`,0.06)&!`Button 5`&!pulse(!`Button 5`,0.1)
```

`pulse(x, seconds)` outputs true for a short window right after `x` changes. So `Triggers/L` only fires if the
left flipper is held, the right flipper is *not* held, and neither flipper has changed state in the last
60-100ms. That last condition is what swallows the brief single-flipper blip while you're pulling both
flippers for a trick, so it just gets treated as D-pad up instead.

### It's not perfect

There's one edge case I haven't solved: if you're leaning into a bike slipstream with the right trigger held
and try to throw an item with the left trigger at the same time, both flippers end up pressed together. The
logic above can't tell that apart from a trick, so it cancels the right trigger, ignores the left, and fires
the trick instead.

Aside from that, it works really well, and it's a fun way to get a racing wheel doing something other than a
traditional racing game!
