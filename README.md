# Easy Arcade

A simple arcade emulator that runs in your web browser, made for tablets.

- Big game tiles: tap a game to play it
- On-screen joystick and buttons, or a Bluetooth gamepad
- Save and Load buttons, plus a "Continue" option when you come back to a game
- Two engines: **FinalBurn Neo** (the default) and **MAME 2003-Plus** (try it if a game won't start)

## Using it

1. Open the site and tap **Add games**.
2. Pick your arcade game `.zip` files. Keep the original set names, such as `pacman.zip` or `sf2.zip`.
3. Tap a game, then press **Coin** and **Start**.

Game files are stored in your own browser on your device. They are never uploaded anywhere.

Some games need extra files to sit alongside them:

- **Neo Geo games** need `neogeo.zip`.
- **Clones** need their parent set, for example `pacman` needs `puckman`.

Add those files the same way as games. Easy Arcade spots BIOS files automatically and passes them, along with small parent sets, to each game.

Only play ROMs you're legally allowed to use.

## Credits and licences

- [EmulatorJS](https://github.com/EmulatorJS/EmulatorJS) 4.2.3 (GPL-3.0), in `ejs/`. It is lightly patched to load pre-unpacked cores from this site: search for `patched` in `ejs/data/emulator.min.js`.
- [FinalBurn Neo](https://github.com/finalburnneo/FBNeo) libretro core. Non-commercial licence, see `ejs/data/cores/fbneo_license.txt`.
- [MAME 2003-Plus](https://github.com/libretro/mame2003-plus-libretro) libretro core. MAME non-commercial licence, see `ejs/data/cores/mame2003_plus_license.txt`.

The interface (`index.html`) was made for personal, non-commercial use.
