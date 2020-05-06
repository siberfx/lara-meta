<?php

namespace Siberfx\LaraMeta\Tests;

use PHPUnit\Framework\TestCase;
use Siberfx\LaraMeta\Meta;

class Tests extends TestCase
{
    protected static $title;
    protected $meta;

    public function setUp()
    {
        self::$title = self::text(20);

        $this->meta = new Meta([
            'title_limit' => 70,
            'description_limit' => 200,
            'image_limit' => 5
        ]);
    }

    protected static function text($length)
    {
        $base = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $text = '';

        while (mb_strlen($text) < $length) {
            $text .= str_shuffle($base);
        }

        return mb_substr($text, 0, $length);
    }

    public function testMetaTitle()
    {
        $response = $this->meta->set('title', $text = self::text(50));

        $this->assertTrue($text === $response);

        $response = $this->meta->set('title', $text = self::text(80));

        $this->assertNotTrue($text, $response);
        $this->assertTrue(mb_strlen($response) === 70);
    }

    public function testMetaDescription()
    {
        $response = $this->meta->set('description', $text = self::text(50));

        $this->assertTrue($text === $response);

        $response = $this->meta->set('description', $text = self::text(250));

        $this->assertNotTrue($text === $response);
        $this->assertTrue(mb_strlen($response) === 200);
    }

    public function testMetaTitleWithTitle()
    {
        $response = $this->meta->title(self::$title);

        $this->assertTrue(self::$title === $response);

        $response = $this->meta->set('title', $text = self::text(30));

        $this->assertTrue($text.' - '.self::$title === $response);

        $response = $this->meta->set('title', $text = self::text(80));

        $this->assertNotTrue($text.' - '.self::$title === $response);
        $this->assertTrue(mb_strlen($response) === 70);
    }

    public function testMetaImage()
    {
        $response = $this->meta->set('image', $text = self::text(30));

        $this->assertTrue($text === $response);

        $response = $this->meta->set('image', $text = self::text(150));

        $this->assertTrue($text === $response);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->meta->set('image', $text =self::text(80));

            if ($i > 2) {
                $this->assertTrue(empty($response));
            } else {
                $this->assertTrue($text === $response);
            }
        }

        $this->assertTrue(count($this->meta->get('image')) === 5);
    }

    public function testTagTitle()
    {
        $this->meta->title(self::$title);
        $this->meta->set('title', $text = self::text(20));

        $tag = $this->meta->tag('title');

        $this->assertTrue(mb_substr_count($tag, '<meta name="title"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:title"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:title"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<title>') === 1);
        $this->assertTrue(mb_strstr($tag, self::$title) ? true : false);
        $this->assertTrue(mb_strstr($tag, $text) ? true : false);
    }

    public function testTagDescription()
    {
        $this->meta->set('description', $text = self::text(150));

        $tag = $this->meta->tag('description');

        $this->assertTrue(mb_substr_count($tag, '<meta name="description"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:description"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:description"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<description>') === 0);
        $this->assertTrue(mb_strstr($tag, $text) ? true : false);
    }

    public function testTagImage()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->meta->set('image', self::text(80));
        }

        $tag = $this->meta->tag('image');

        $this->assertTrue(mb_substr_count($tag, '<meta name="image"') === 5);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:image"') === 5);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:image"') === 5);
        $this->assertTrue(mb_substr_count($tag, '<image>') === 0);
        $this->assertTrue(mb_substr_count($tag, '<link rel="image_src"') === 5);
    }

    public function testTagImageDefault()
    {
        $tag = $this->meta->tag('image', self::text(80));

        $this->assertTrue(mb_substr_count($tag, '<meta name="image"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:image"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:image"') === 1);
        $this->assertTrue(mb_substr_count($tag, '<image>') === 0);
        $this->assertTrue(mb_substr_count($tag, '<link rel="image_src"') === 1);

        for ($i = 0; $i < 3; $i++) {
            $this->meta->set('image', self::text(80));
        }

        $tag = $this->meta->tag('image');

        $this->assertTrue(mb_substr_count($tag, '<meta name="image"') === 3);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:image"') === 3);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:image"') === 3);
        $this->assertTrue(mb_substr_count($tag, '<image>') === 0);
        $this->assertTrue(mb_substr_count($tag, '<link rel="image_src"') === 3);

        $tag = $this->meta->tag('image', self::text(80));

        $this->assertTrue(mb_substr_count($tag, '<meta name="image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<image>') === 0);
        $this->assertTrue(mb_substr_count($tag, '<link rel="image_src"') === 4);

        $tag = $this->meta->tag('image', self::text(80));

        $this->assertTrue(mb_substr_count($tag, '<meta name="image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<meta name="twitter:image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<meta property="og:image"') === 4);
        $this->assertTrue(mb_substr_count($tag, '<image>') === 0);
        $this->assertTrue(mb_substr_count($tag, '<link rel="image_src"') === 4);
    }
}
