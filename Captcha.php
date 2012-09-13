<?php

namespace Innocead\CaptchaBundle;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Response;

class Captcha
{

    protected $config = array();
    protected $image;
    protected $temp_image;
    protected $captcha = array();
    protected $imageContent;

    /**
     * @var Session
     */
    protected $session;

    public function __construct(Session $session, $conf = array())
    {
        $this->session = $session;

        $this->config = array(
            'width' => 100,
            'height' => 20,
            'bg_red' => 238,
            'bg_green' => 255,
            'bg_blue' => 255,
            'bg_transparent' => true,
            'bg_img' => false,
            'bg_border' => false,
            //Setting characters
            'char_red' => 0,
            'char_green' => 0,
            'char_blue' => 0,
            'char_random_color' => true,
            'char_random_color_lvl' => 2,
            'char_transparent' => 10,
            'char_px_spacing' => 20,
            'char_min_size' => 14,
            'char_max_size' => 14,
            'char_max_rot_angle' => 30,
            'char_vertical_offset' => true,
            //Setting fonts
            'char_fonts' => array('luggerbu.ttf'),
            'char_fonts_dir' => __DIR__ . '/Resources/fonts',
            //Setting authorized characters
            'chars_used' => 'ABCDEFGHKLMNPRTWXYZ234569',
            //Setting easy captchas
            'easy_captcha' => false,
            'easy_captcha_vowels' => 'AEIOUY',
            'easy_captcha_consonants' => 'BCDFGHKLMNPRTVWXZ',
            'easy_captcha_bool' => rand(0, 1),
            //Setting parameters
            'case_sensitive' => false,
            'min_chars' => 3,
            'max_chars' => 4,
            'brush_size' => 1,
            'format' => 'png',
            'hash_algo' => 'sha1',
            'flood_timer' => 0,
            'max_refresh' => 1000,
            'effect_blur' => false,
            'effect_greyscale' => false,
            'noise_min_px' => 0,
            'noise_max_px' => 0,
            'noise_min_lines' => 0,
            'noise_max_lines' => 0,
            'noise_min_circles' => 0,
            'noise_max_circles' => 0,
            'noise_color' => 3,
            'noise_on_top' => false,
            'error_images_dir' => __DIR__ . '/Resources/error',
            //test
            'test_queries_flood' => false,
        );

        if (count($conf)) {
            $this->config = array_merge($this->config, $conf);
        }

        if (true === $this->config['test_queries_flood']) {

            if ($this->testQueries() && $this->testLastRequest()) {
                $this->config['constructor_test'] = true;
            } else {
                $this->config['constructor_test'] = false;
                if (!$this->testQueries()) {
                    $this->config['constructor_error_reason'] = 'Error - too many queries';
                    $this->config['constructor_error_message'] = 'too_many';
                } elseif (!$this->testLastRequest()) {
                    $this->config['constructor_error_reason'] = 'Error - refreshing too fast';
                    $this->config['constructor_error_message'] = 'refresh';
                } else {
                    $this->config['constructor_error_reason'] = 'Error - unknown reason';
                    $this->config['constructor_error_message'] = 'unknown';
                }

                return false;
            }
        } else {
            $this->config['constructor_test'] = true;
        }
    }

    protected function render()
    {
        if ($this->config['constructor_test'] === false) {
            $this->generateErrorImage($this->config['constructor_error_message']);

            return true;
        }

        $this->generateCaptcha();
        $this->adjustCaptcha();

        imagedestroy($this->temp_image);

        $this->selectBackground();
        $this->createCaptchaImageAndBackground();
        $this->setNoiseParameters();

        if ($this->config['noise_on_top']) {
            $this->addLettersToImage();
            $this->addNoiseToImage();
        } else {
            $this->addNoiseToImage();
            $this->addLettersToImage();
        }
        $this->clearBrush();

        $this->addBorder();
        $this->addEffect();

        $this->setSessionAttributes();
    }

    public function isValid($input)
    {
        if (!is_scalar($input)) {
            return false;
        }

        if (!$this->config['case_sensitive']) {
            $input = strtoupper($input);
        }

        if (empty($this->config['hash_algo'])) {
            $input_hash = hash('sha1', $input);
        } else {
            $input_hash = hash($this->config['hash_algo'], $input);
        }

        $saved_captcha = $this->session->get('innocead.captcha.code');
        if ($input_hash == $saved_captcha) {
            $this->session->remove('innocead.captcha.code');
            $this->session->remove('innocead.captcha.last_request');
            $this->session->remove('innocead.captcha.queries');

            return true;
        } else {
            return false;
        }

        return false;
    }

    /**
     * @return Response
     */
    public function generateResponse()
    {
        $this->render();

        $response = new Response();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('must-revalidate');

        $response->headers->addCacheControlDirective('post-check', 0);
        $response->headers->addCacheControlDirective('pre-check', 0);

        $response->headers->set('Pragma', 'no-cache');

        $response->setLastModified(new \DateTime());
        $response->setExpires(new \DateTime());

        if ($this->imageContent) {
            $response->setContent($this->imageContent);
        } else {
            ob_start();

            switch (strtoupper($this->config['format'])) {
                case 'JPEG':
                case 'JPG':
                    $response->headers->set('Content-Type', 'image/jpeg');
                    imagejpeg($this->image, null, 90);
                    break;

                case 'GIF':
                    $response->headers->set('Content-Type', 'image/gif');
                    imagegif($this->image);
                    break;

                default:
                    $response->headers->set('Content-Type', 'image/png');
                    imagepng($this->image);
                    break;
            }

            $content = ob_get_clean();
            $response->setContent($content);

            imagedestroy($this->image);
        }

        switch (strtoupper($this->config['format'])) {
            case 'JPEG':
            case 'JPG':
                $response->headers->set('Content-Type', 'image/jpeg');
                break;

            case 'GIF':
                $response->headers->set('Content-Type', 'image/gif');
                break;

            default:
                $response->headers->set('Content-Type', 'image/png');
                break;
        }

        return $response;
    }

    private function generateCaptcha()
    {
        $this->captcha['word'] = '';
        $this->captcha['chars'] = rand($this->config['min_chars'], $this->config['max_chars']);

        $x_coord = 10;

        for ($i = 1; $i <= $this->captcha['chars']; $i++) {
            //font
            $this->captcha['letters'][$i]['font'] = $this->getRandomFont();
            $this->captcha['letters'][$i]['font_path'] = $this->config['char_fonts_dir'] . DIRECTORY_SEPARATOR . $this->captcha['letters'][$i]['font'];

            //ink color
            $this->captcha['letters'][$i]['ink_type'] = $this->getInkType();
            $this->captcha['letters'][$i]['ink_colors'] = $this->getInkColors();

            //rotation
            $this->captcha['letters'][$i]['rotation'] = $this->getRandomLetterRotation();

            //character
            $this->captcha['letters'][$i]['char'] = $this->getRandomCharacter();

            //size
            $this->captcha['letters'][$i]['size'] = $this->getRandomCharacterSize();

            //vertical offset of the letter
            $this->captcha['letters'][$i]['y_coord'] = $this->getRandomVerticalOffset();

            //Add the letter to the complete word
            $this->captcha['word'] .= $this->captcha['letters'][$i]['char'];

            //save the letter coordinate and increase the counter
            $this->captcha['letters'][$i]['x_coord'] = $x_coord;
            $x_coord += $this->config['char_px_spacing'];
        }

        return true;
    }

    private function adjustCaptcha()
    {
        //generate temporary image
        $this->generateRawTempImage();

        //add chars into the image
        $this->addCaptchaOnTempImage();

        //adjust the temp captcha and get the offset
        $x_coord_adjust = $this->getAdjustmentOffset();
        if (!empty($x_coord_adjust)) {
            $this->captcha['x_coord_adjust'] = $x_coord_adjust;

            //update characters x coord with the new $xadjustment
            $this->updateCaptchaXCoord();

            return true;
        } else {
            return false;
        }
    }

    /**
     * Randomly selects a font in the available fonts
     *
     * @return string The randomly selected font
     */
    private function getRandomFont()
    {
        return $this->config['char_fonts'][array_rand($this->config['char_fonts'], 1)];
    }

    /**
     * Randomly generates an angle
     *
     * @return string The randomly generated angle
     */
    private function getRandomLetterRotation()
    {
        if (rand(0, 1)) {
            return rand(0, $this->config['char_max_rot_angle']);
        } else {
            return rand(360 - $this->config['char_max_rot_angle'], 360);
        }
    }

    /**
     * Randomly selects a character
     *
     * The function randomly selects characters to make the captcha
     * word. If the easy captcha is activated, it alternates
     * between vowels and consonants.
     *
     * @return string The randomly selected letter
     */
    private function getRandomCharacter()
    {
        //test if easy captcha activated    
        if ($this->config['easy_captcha']) {
            //select if vowel or consonant
            if ($this->config['easy_captcha_bool'] == 1) {
                //invert for next letter (vowel/consonant)
                $this->config['easy_captcha_bool'] = 0;

                return $this->config['easy_captcha_consonants']{rand(
                    0,
                    strlen($this->config['easy_captcha_consonants']) - 1
                )};
            } else {
                //invert for next letter (vowel/consonant)
                $this->config['easy_captcha_bool'] = 1;

                return $this->config['easy_captcha_vowels']{rand(0, strlen($this->config['easy_captcha_vowels']) - 1)};
            }
        } else {
            //random character in the authorized characters
            return $this->config['chars_used']{rand(0, strlen($this->config['chars_used']) - 1)};
        }
    }

    /**
     * Randomly selects a character size
     *
     * @return string The randomly selected size
     */
    private function getRandomCharacterSize()
    {
        return rand($this->config['char_min_size'], $this->config['char_max_size']);
    }

    /**
     * Randomly generates a vertical offset
     *
     * @return string The randomly generated offset
     */
    private function getRandomVerticalOffset()
    {
        if ($this->config['char_vertical_offset']) {
            $vertical_offset = ($this->config['height'] / 2) + 4;
            $vertical_offset += rand(0, round($this->config['height'] / 5));
        } else {
            $vertical_offset = round($this->config['height'] / 1.5);
        }

        return $vertical_offset;
    }

    /**
     * Generates an image with a white background in the $temp_image class attribute
     *
     * @return bool True if the generation is successfull.
     */
    private function generateRawTempImage()
    {
        $this->temp_image = imagecreatetruecolor($this->config['width'], $this->config['height']);
        $white = imagecolorallocate($this->temp_image, 255, 255, 255);

        if (!imagefill($this->temp_image, 0, 0, $white)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Adds the captcha letters on the temporary image
     *
     * @return bool True if the adding is successfull
     */
    private function addCaptchaOnTempImage()
    {
        //add each character
        for ($i = 1; $i <= $this->captcha['chars']; $i++) {
            $black_ink = imagecolorallocate($this->temp_image, 0, 0, 0);
            //add letter to image
            if (!imagettftext(
                $this->temp_image,
                $this->captcha['letters'][$i]['size'],
                $this->captcha['letters'][$i]['rotation'],
                $this->captcha['letters'][$i]['x_coord'],
                $this->captcha['letters'][$i]['y_coord'],
                $black_ink,
                $this->captcha['letters'][$i]['font_path'],
                $this->captcha['letters'][$i]['char']
            )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Computes the x coordinate offset
     *
     * @return int The x coordiante offset
     */
    private function getAdjustmentOffset()
    {
        $white = imagecolorallocate($this->temp_image, 255, 255, 255);

        //Adjust the X begin coordinate
        $xbegin = 0;
        $x = 0;
        while ($x < $this->config['width'] && !$xbegin) {
            $y = 0;
            while ($y < $this->config['height'] && !$xbegin) {
                if (imagecolorat($this->temp_image, $x, $y) != $white) {
                    $xbegin = $x;
                }
                $y++;
            }
            $x++;
        }
        $xend = 0;

        //Adjust the X end coordinate
        $x = $this->config['width'] - 1;
        while ($x > 0 && !$xend) {
            $y = 0;
            while ($y < $this->config['height'] && !$xend) {
                if (imagecolorat($this->temp_image, $x, $y) != $white) {
                    $xend = $x;
                }
                $y++;
            }
            $x--;
        }

        //Compute the adjustment
        $xadjustment = round(($this->config['width'] / 2) - ($xend - $xbegin) / 2);

        return $xadjustment;
    }

    /**
     * Selects the final captcha background. Random image if file in configuration
     *
     * In all cases, it defines also the background color.
     *
     * @return bool True if image correctly selected and set
     */
    private function selectBackground()
    {
        if ($this->config['bg_img'] && is_dir($this->config['bg_img'])) {
            $pointer = opendir($this->config['bg_img']);
            while (false !== ($filename = readdir($pointer))) {
                if (eregi('.[gif|jpg|jpeg|png]$', $filename)) {
                    $files[] = $filename;
                }
            }
            closedir($pointer);
            $this->captcha['bg_img'] = $this->config['bg_img'] . '/' . $files[array_rand($files, 1)];
        } elseif ($this->config['bg_img'] && file_exists($this->config['bg_img'])) {
            //use the file specified
            $this->captcha['bg_img'] = $this->config['bg_img'];
        } else {
            $this->captcha['bg_img'] = '';
        }
        $this->captcha['bg_colors'] = array(
            'red' => $this->config['bg_red'],
            'green' => $this->config['bg_green'],
            'blue' => $this->config['bg_blue']
        );

        if (!empty($this->captcha['bg_colors'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Selects the ink type (transparent or normal)
     *
     * @return bool The ink type (true=alpha, false=opaque)
     */
    private function getInkType()
    {
        //select if transparent or "normal"
        if (function_exists('imagecolorallocatealpha') && $this->config['char_transparent'] != 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Selects the ink colors
     *
     * @return array The ink colors
     */
    private function getInkColors()
    {
        $ink = array();
        //random color or configured colors
        if ($this->config['char_random_color']) {
            $ok = false;
            do {
                $rand_red = rand(0, 255);
                $rand_green = rand(0, 255);
                $rand_blue = rand(0, 255);
                $random_color_sum = $rand_red + $rand_green + $rand_blue;
                switch ($this->config['char_random_color_lvl']) {
                    case 1 :
                        if ($random_color_sum < 200) {
                            $ok = true;
                        }
                        break; // very dark
                    case 2 :
                        if ($random_color_sum < 400)
                            $ok = true;
                        break; // dark
                    case 3 :
                        if ($random_color_sum > 500)
                            $ok = true;
                        break; // bright
                    case 4 :
                        if ($random_color_sum > 650)
                            $ok = true;
                        break; // very bright
                    default :
                        $ok = true;
                }
            } while ($ok == false);

            $ink['red'] = $rand_red;
            $ink['green'] = $rand_green;
            $ink['blue'] = $rand_blue;
        } else {
            $ink['red'] = $this->config['char_red'];
            $ink['green'] = $this->config['char_green'];
            $ink['blue'] = $this->config['char_blue'];
        }

        return $ink;
    }

    /**
     * Defines the captcha noise parameters
     *
     * @return bool Always true
     */
    private function setNoiseParameters()
    {
        switch ($this->config['noise_color']) {
            case 1 :
                $rand_letter = rand(1, $this->captcha['chars']);
                $this->captcha['noise_color'] = $this->captcha['letters'][$rand_letter]['ink_colors'];
                break; //color of the writing
            case 2 :
                $this->captcha['noise_color'] = $this->captcha['bg_colors'];
                break; //color of the background
            case 3 :
            default :
                $this->captcha['noise_color'] = array(
                    'red' => rand(0, 255),
                    'green' => rand(0, 255),
                    'blue' => rand(0, 255)
                );
                break; //random color
        }

        $this->captcha['noise_px'] = rand($this->config['noise_min_px'], $this->config['noise_max_px']);
        $this->captcha['noise_lines'] = rand($this->config['noise_min_lines'], $this->config['noise_max_lines']);
        $this->captcha['noise_circles'] = rand($this->config['noise_min_circles'], $this->config['noise_max_circles']);

        return true;
    }

    /**
     * Generates the final captcha image with the background
     *
     * @return bool Always true
     */
    private function createCaptchaImageAndBackground()
    {
        $this->image = imagecreatetruecolor($this->config['width'], $this->config['height']);
        //add background
        if (!empty($this->config['bg_img'])) {
            $this->addImageBackgroundImage();
        } else {
            $this->addImageBackgroundColor();
        }

        return true;
    }

    /**
     * Adds the background image to the $image attribute
     *
     * @return bool Always true
     */
    private function addImageBackgroundImage()
    {
        list($bg_width, $bg_height, $bg_type, $bg_attributes) = getimagesize($this->captcha['bg_img']);
        if ($bg_type == '1') {
            $img_read = imagecreatefromgif($this->captcha['bg_img']);
        } elseif ($bg_type == '2') {
            $img_read = imagecreatefromjpeg($this->captcha['bg_img']);
        } elseif ($bg_type == '3') {
            $img_read = imagecreatefrompng($this->captcha['bg_img']);
        } else {
            return false;
        }
        imagecopyresized(
            $this->image,
            $img_read,
            0,
            0,
            0,
            0,
            $this->config['width'],
            $this->config['height'],
            $bg_width,
            $bg_height
        );
        imagedestroy($img_read);

        return true;
    }

    /**
     * Adds the background color to the $image attribute
     *
     * @return bool Always true
     */
    private function addImageBackgroundColor()
    {
        $bg = imagecolorallocate(
            $this->image,
            $this->captcha['bg_colors']['red'],
            $this->captcha['bg_colors']['green'],
            $this->captcha['bg_colors']['blue']
        );
        imagefill($this->image, 0, 0, $bg);

        if ($this->config['bg_transparent'] && strtoupper($this->config['format']) == 'PNG') {
            imagecolortransparent($this->image, $bg);
        }

        return true;
    }

    /**
     * Adds the noise to the $image attribute
     *
     * @return bool Always true
     */
    private function addNoiseToImage()
    {
        //add pixels
        for ($i = 1; $i <= $this->captcha['noise_px']; $i++) {
            imagesetpixel(
                $this->image,
                rand(0, $this->config['width'] - 1),
                rand(0, $this->config['height'] - 1),
                $this->getNoiseBrush()
            );
        }

        for ($j = 1; $j <= $this->captcha['noise_lines']; $j++) {
            imageline(
                $this->image,
                rand(0, $this->config['width'] - 1),
                rand(0, $this->config['height'] - 1),
                rand(0, $this->config['width'] - 1),
                rand(0, $this->config['height'] - 1),
                $this->getNoiseBrush()
            );
        }

        for ($k = 1; $k <= $this->captcha['noise_circles']; $k++) {
            $radius = rand(5, $this->config['width'] / 3);
            imagearc(
                $this->image,
                rand(0, $this->config['width'] - 1),
                rand(0, $this->config['height'] - 1),
                $radius,
                $radius,
                0,
                360,
                $this->getNoiseBrush()
            );
        }

        return true;
    }

    /**
     * Updates the captcha letters x_coord value
     *
     * @return bool Always true
     */
    private function updateCaptchaXCoord()
    {
        //for each letter, redefine the x_coord
        $x_coord = $this->captcha['x_coord_adjust']; //starting position of the captcha on the X axis - adapted with the x offset.

        for ($i = 1; $i <= $this->captcha['chars']; $i++) {
            //update the letter coordinate and increase the counter
            $this->captcha['letters'][$i]['x_coord'] = $x_coord;
            $x_coord += $this->config['char_px_spacing'];
        }

        return true;
    }

    /**
     * Adds the actual captcha letters to the $image attribute
     *
     * @return bool Always true
     */
    private function addLettersToImage()
    {
        //add each character to the image :D
        for ($i = 1; $i <= $this->captcha['chars']; $i++) {
            //create ink
            if ($this->captcha['letters'][$i]['ink_type']) {
                //alpha active
                $ink = imagecolorallocatealpha(
                    $this->image,
                    $this->captcha['letters'][$i]['ink_colors']['red'],
                    $this->captcha['letters'][$i]['ink_colors']['green'],
                    $this->captcha['letters'][$i]['ink_colors']['blue'],
                    $this->config['char_transparent']
                );
            } else {
                //normal/opaque ink
                $ink = imagecolorallocatealpha(
                    $this->image,
                    $this->captcha['letters'][$i]['ink_colors']['red'],
                    $this->captcha['letters'][$i]['ink_colors']['green'],
                    $this->captcha['letters'][$i]['ink_colors']['blue']
                );
            }

            //add character
            imagettftext(
                $this->image,
                $this->captcha['letters'][$i]['size'],
                $this->captcha['letters'][$i]['rotation'],
                $this->captcha['letters'][$i]['x_coord'],
                $this->captcha['letters'][$i]['y_coord'],
                $ink,
                $this->captcha['letters'][$i]['font_path'],
                $this->captcha['letters'][$i]['char']
            );
            //char added :)
        }

        return true;
    }

    /**
     * Defines the brush used on the $image attribute
     *
     * @return bool Always true
     */
    private function setBrush()
    {
        $noise_color = imagecolorallocate(
            $this->image,
            $this->captcha['noise_color']['red'],
            $this->captcha['noise_color']['green'],
            $this->captcha['noise_color']['blue']
        );
        if ($this->config['brush_size'] && $this->config['brush_size'] > 1 && function_exists('imagesetbrush')) {
            $brush = imagecreatetruecolor($this->config['brush_size'], $this->config['brush_size']);
            imagefill($brush, 0, 0, $noise_color);
            imagesetbrush($this->image, $brush);
            $this->captcha['noise_brush'] = IMG_COLOR_BRUSHED;
            $this->captcha['brush'] = $brush;
        } else {
            $this->captcha['noise_brush'] = $noise_color;
        }

        return true;
    }

    /**
     * Deletes the brush image so it can be reused!
     *
     * @return bool Always returns true
     */
    private function clearBrush()
    {
        if (isset($this->captcha['brush']) && !empty($this->captcha['brush'])) {
            imagedestroy($this->captcha['brush']);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Refreshes the captcha noise parameters if the random option is selected
     *
     * @return bool If changed, returns true
     */
    private function refreshNoiseColor()
    {
        if ($this->config['noise_color'] != 1 && $this->config['noise_color'] != 2) {
            $this->captcha['noise_color'] = array(
                'red' => rand(0, 255),
                'green' => rand(0, 255),
                'blue' => rand(0, 255)
            );

            return true;
        }

        return false;
    }

    /**
     * Refreshes the noise and makes it into a brush for direct use
     *
     * @return bool Always true
     */
    private function getNoiseBrush()
    {
        //refresh the color if random type selected
        if ($this->refreshNoiseColor()) {
            //brush updated, regenerate brush
            $this->setBrush();
        } else {
            if (empty($this->captcha['noise_brush']) || !isset($this->captcha['noise_brush'])) {
                $this->setBrush();
            } else {
                //no need to do anything (color not updated and brush set)
            }
        }

        return $this->captcha['noise_brush'];
    }

    /**
     * Adds a border around the image
     *
     * @return bool Always true
     */
    private function addBorder()
    {
        if ($this->config['bg_border']) {
            $border_color = imagecolorallocate(
                $this->image,
                ($this->config['bg_red'] * 3 + $this->config['char_red']) / 4,
                ($this->config['bg_green'] * 3 + $this->config['char_green']) / 4,
                ($this->config['bg_blue'] * 3 + $this->config['char_blue']) / 4
            );
            imagerectangle($this->image, 0, 0, $this->config['width'] - 1, $this->config['height'] - 1, $border_color);
        }

        return true;
    }

    /**
     * Aplies effects to the image
     *
     * @return bool Always true
     */
    private function addEffect()
    {
        if (function_exists('imagefilter')) {
            if ($this->config['effect_greyscale']) {
                imagefilter($this->image, IMG_FILTER_GRAYSCALE);
            }
            if ($this->config['effect_blur']) {
                imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }

        return true;
    }

    /**
     * Saves the captcha word into the user session
     *
     * @return bool Always true
     */
    private function setSessionAttributes()
    {
        if (!$this->config['case_sensitive']) {
            $this->captcha['word'] = strtoupper($this->captcha['word']);
        }

        //save the captcha into the session in hashed form
        if (empty($this->config['hash_algo'])) {
            $this->session->set('innocead.captcha.code', hash('sha1', $this->captcha['word']));
        } else {
            $this->session->set('innocead.captcha.code', hash($this->config['hash_algo'], $this->captcha['word']));
        }

        return true;
    }

    private function testQueries()
    {
        if ($this->session->get('innocead.captcha.queries') == '' || $this->session->get(
            'innocead.captcha.queries'
        ) == 0
        ) {
            $this->session->set('innocead.captcha.queries', 1);

            return true;
        } elseif ($this->session->get('innocead.captcha.queries') >= $this->config['max_refresh']) {
            return false;
        } else {
            $this->session->set('innocead.captcha.queries', $this->session->get('innocead.captcha.queries') + 1);

            return true;
        }
    }

    private function testLastRequest()
    {
        if ($this->session->get('innocead.captcha.last_request') == '' || $this->session->get(
            'innocead.captcha.last_request'
        ) == 0
        ) {
            $this->session->set('innocead.captcha.last_request', time());

            return true;
        } else {
            $delay = time() - $this->session->get('innocead.captcha.last_request');
            if ($this->config['flood_timer'] != 0 && $this->config['flood_timer'] > $delay) {
                return false;
            } else {
                $this->session->set('innocead.captcha.last_request', time());

                return true;
            }
        }
    }

    private function generateErrorImage($error_message)
    {
        $image_path = $this->config['error_images_dir'] . DIRECTORY_SEPARATOR . $error_message . '.' . $this->config['format'];
        $this->imageContent = file_get_contents($image_path);
    }

}
