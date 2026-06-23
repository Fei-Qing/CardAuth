<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Validator;

/**
 * 输入验证器单元测试
 */
class ValidatorTest extends TestCase
{
    public function testRequiredValidation(): void
    {
        $validator = new Validator(['name' => '']);
        $this->assertFalse($validator->validate(['name' => 'required']));

        $validator = new Validator(['name' => 'CardAuth']);
        $this->assertTrue($validator->validate(['name' => 'required']));
    }

    public function testEmailValidation(): void
    {
        $validator = new Validator(['email' => 'admin@example.com']);
        $this->assertTrue($validator->validate(['email' => 'email']));

        $validator = new Validator(['email' => 'not-an-email']);
        $this->assertFalse($validator->validate(['email' => 'email']));
    }

    public function testMinMaxLength(): void
    {
        $validator = new Validator(['site_name' => 'CA']);
        $this->assertFalse($validator->validate(['site_name' => 'min:3']));

        $validator = new Validator(['site_name' => 'CardAuth']);
        $this->assertTrue($validator->validate(['site_name' => 'min:3|max:50']));

        $validator = new Validator(['site_name' => str_repeat('a', 51)]);
        $this->assertFalse($validator->validate(['site_name' => 'max:50']));
    }

    public function testIntegerValidation(): void
    {
        $validator = new Validator(['expire' => '15']);
        $this->assertTrue($validator->validate(['expire' => 'integer']));

        $validator = new Validator(['expire' => '15.5']);
        $this->assertFalse($validator->validate(['expire' => 'integer']));
    }

    public function testInValidation(): void
    {
        $validator = new Validator(['status' => 'unused']);
        $this->assertTrue($validator->validate(['status' => 'in:unused,used,disabled']));

        $validator = new Validator(['status' => 'expired']);
        $this->assertFalse($validator->validate(['status' => 'in:unused,used,disabled']));
    }
}
