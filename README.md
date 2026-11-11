# Easy Arcade

A simple arcade emulator that runs in your web browser, made for tablets.

- A start screen with three choices: **Add games**, **Game library** and **Settings**
- Big game tiles: tap a game to play it. You can add your own artwork to each one.
- Add games by tapping **Choose files** or by dragging and dropping. A progress bar shows each file as it's added.
- On-screen joystick and buttons, or a Bluetooth/USB gamepad. Controller and keyboard buttons can be remapped in Settings.
- Save and Load buttons, plus a "Continue" option when you come back to a game
- Two engines: **FinalBurn Neo** (the default) and **MAME 2003-Plus** (try it if a game won't start)

## Using it

1. Open the site and tap **Add games**. The first time, you'll be asked to confirm you have the right to use the games you add.
2. Choose or drop your arcade game `.zip` files. Keep the original set names, such as `pacman.zip` or `sf2.zip`.
   To add artwork, include pictures with the same names, such as `pacman.png`, or drop a picture onto a game tile.
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

## Machine buttons and game settings

Some games use switches from inside the real arcade cabinet. Tap **🛠️ Machine** in the game bar for:

- **Service / Advance** (F2): moves past start-up test screens, for example in Defender, Robotron and Joust.
- **Restart machine** (F3).
- **Extra 1 / Extra 2** (E / D): game-specific buttons, for example Space Duel's game-type select.
- **Player 2 coin / start** (6 / 2).
- **Game settings**: the machine's DIP switches, such as lives, difficulty and coins per credit. These are saved for each game.

## Artwork finder

**🔎 Find artwork** in the library goes through each game that has no picture and offers its flyer, title screen and screenshot from the [libretro-thumbnails](https://github.com/libretro-thumbnails) project. `art-index.json` maps each game file name to its pictures; it's built from the FinalBurn Neo arcade game list.

## Online library (on your own web hosting)

When Easy Arcade runs from your own hosting, `api.php` can keep your games, artwork, settings and saves on your website, so they appear on every device. Most hosting includes PHP, which this needs (PHP 7.4 or newer).

1. Upload the `easy-arcade` folder to your hosting, for example `public_html/easy-arcade/`.
2. Open it in a browser and tap **Set up your online library** to choose a password.
3. On other devices, tap **Sign in to your online library** and enter the same password.

- **Where files are kept:** everything goes in `data/`, inside a sub-folder with a random name. The script creates this the first time you use it.
- **Back-ups:** to keep a copy of your library, download the `data` folder.
- **Forgotten password:** delete `data/config.php`, then open Easy Arcade to choose a new one. Your games stay where they are.
- **Keep it private:** don't make the `data` folder public or share its contents.
