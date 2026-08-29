<?php

namespace Tests\Unit;

use App\Services\CustomFieldEngine;
use Tests\TestCase;

class CustomFieldEngineTest extends TestCase
{
    public function testTextFieldHandler(): void
    {
        $handler = CustomFieldEngine::getHandler('text');
        $this->assertEquals('value_text', $handler->getStorageColumn());
        $this->assertTrue($handler->validate('Sample Text'));
        $this->assertEquals('Sample Text', $handler->serialize('Sample Text'));
        $this->assertEquals('Sample Text', $handler->deserialize('Sample Text'));
    }

    public function testNumberFieldHandler(): void
    {
        $handler = CustomFieldEngine::getHandler('number');
        $this->assertEquals('value_number', $handler->getStorageColumn());
        $this->assertTrue($handler->validate(42.5));
        $this->assertEquals(42.5, $handler->serialize('42.5'));
        $this->assertEquals(42.5, $handler->deserialize(42.5));
    }

    public function testSelectFieldHandler(): void
    {
        $handler = CustomFieldEngine::getHandler('select');
        $options = ['Design', 'Engineering', 'QA'];
        $this->assertTrue($handler->validate('Engineering', $options));
        $this->assertFalse($handler->validate('Marketing', $options));
    }

    public function testMultiSelectFieldHandler(): void
    {
        $handler = CustomFieldEngine::getHandler('multi_select');
        $this->assertEquals('value_json', $handler->getStorageColumn());
        $val = ['Alpha', 'Beta'];
        $serialized = $handler->serialize($val);
        $this->assertEquals('["Alpha","Beta"]', $serialized);
        $this->assertEquals($val, $handler->deserialize($serialized));
    }

    public function testCheckboxFieldHandler(): void
    {
        $handler = CustomFieldEngine::getHandler('checkbox');
        $this->assertEquals(1, $handler->serialize(true));
        $this->assertEquals(0, $handler->serialize(false));
        $this->assertTrue($handler->deserialize(1));
        $this->assertFalse($handler->deserialize(0));
    }
}
