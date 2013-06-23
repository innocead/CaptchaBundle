# Configuring

There's easy way to configure captcha - just add `innocead_captcha` to `config.yml` and change default values.

## Default options

``` yaml
innocead_captcha:
    width: 100
    height: 20
    max_chars: 4
    min_chars: 3
    char_max_size: 14
    char_min_size: 14
    char_transparent: 10
    char_px_spacing: 20
    char_random_color_lvl: 4                # (1 - very dark, 2 - dark, 3 - bright, 4 - very bright)
    char_max_rot_angle:  30
    effect_greyscale: false
    effect_blur: false
    noise_min_px: 0
    noise_max_px: 0
    noise_min_lines: 0
    noise_max_lines: 0
    noise_min_circles: 0
    noise_max_circles: 0
    noise_on_top: false
    noise_color: 3                           # (1 - color of the writing, 2 - color of the background, 3 - random color)
    brush_size: 1                            # brush noise size
    bg_transparent: true                     # transparent background
    bg_red: 238                              # used if background is not transparen 
    bg_green: 255
    bg_blue: 255
    bg_border: false
    flood_timer: 0                           # min difference between last request and current request
    max_refresh: 1000                        # refreshes before user enter a valid code or expare session
    test_queries_flood: false                # test for request refreshes and flood timer
    chars_used: "ABCDEFGHKLMNPRTWXYZ234569"
    char_fonts:                              # array of used fonts (can be luggerbu.ttf, elephant.ttf, scrawl.ttf, alanden.ttf)
      - luggerbu.ttf    
```