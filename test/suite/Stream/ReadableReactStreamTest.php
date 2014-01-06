<?php
namespace Recoil\Stream;

use Exception;
use Recoil\Recoil;
use Recoil\Stream\Exception\StreamClosedException;
use Recoil\Stream\Exception\StreamReadException;
use Phake;
use PHPUnit_Framework_TestCase;
use React\Stream\Stream;

class ReadableReactStreamTest extends PHPUnit_Framework_TestCase
{
    use ReadableStreamTestTrait;

    public function createStream()
    {
        $this->reactStream = new Stream($this->resource, $this->eventLoop);

        return new ReadableReactStream($this->reactStream);
    }

    public function testReadFailure()
    {
        $this->setExpectedException(StreamReadException::CLASS);

        Phake::when($this->eventLoop)
            ->removeReadStream(Phake::anyParameters())
            ->thenGetReturnByLambda(
                function () {
                    $this->reactStream->emit(
                        'error',
                        [new Exception, $this->reactStream]
                    );
                }
            );

        Recoil::run(
            function () {
                yield $this->stream->read(16);
            },
            $this->eventLoop
        );
    }

    public function testCloseBeforeEnd()
    {
        $this->setExpectedException(StreamClosedException::CLASS);

        Recoil::run(
            function () {
                $coroutine = function () {
                    $this->stream->onStreamClose();
                    yield Recoil::noop();
                };

                yield Recoil::execute($coroutine());
                yield $this->stream->read(16);
            },
            $this->eventLoop
        );
    }

    public function testEndBeforeData()
    {
        Recoil::run(
            function () {
                $coroutine = function () {
                    $this->stream->onStreamEnd();
                    yield Recoil::noop();
                };

                yield Recoil::execute($coroutine());

                $buffer = (yield $this->stream->read(16));

                $this->assertSame('', $buffer);
            },
            $this->eventLoop
        );
    }
}