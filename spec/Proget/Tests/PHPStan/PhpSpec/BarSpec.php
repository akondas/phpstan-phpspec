<?php

declare(strict_types=1);

namespace spec\Proget\Tests\PHPStan\PhpSpec;

use Proget\Tests\PHPStan\PhpSpec\Bar;
use PhpSpec\ObjectBehavior;
use Proget\Tests\PHPStan\PhpSpec\Foo;

class BarSpec extends ObjectBehavior
{
    public function let(Foo $foo): void
    {
        $this->beConstructedWith($foo);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(Bar::class);
    }

    public function it_should_return_correct_string(Foo $foo): void
    {
        $foo->foo()->willReturn('correct-string');

        $this->foo()->shouldReturn('correct-string');
    }

    public function it_should_throw_exception(Foo $foo): void
    {
        $foo->foo()->willReturn('shouldThrow');

        $this->shouldThrow(\RuntimeException::class)->during('foo');
    }

    public function it_should_call_void_one_time(Foo $foo): void
    {
        $foo->doSomething()->shouldBeCalledTimes(1);

        $this->bar();
    }
}
