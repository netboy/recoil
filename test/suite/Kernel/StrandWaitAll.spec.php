<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Recoil\Exception\TerminatedException;
use Throwable;

describe(StrandWaitAll::class, function () {

    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(Strand::class);

        $this->substrand1 = Phony::mock(Strand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(Strand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitAll(
            $this->substrand1->mock(),
            $this->substrand2->mock()
        );

        $this->subject->await(
            $this->strand->mock(),
            $this->api->mock()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when all substrands complete', function () {
            $this->strand->setTerminator->calledWith([$this->subject, 'cancel']);
            $this->substrand1->setObserver->calledWith($this->subject);
            $this->substrand2->setObserver->calledWith($this->subject);

            $this->subject->success($this->substrand2->mock(), '<two>');

            $this->strand->resume->never()->called();
            $this->strand->throw->never()->called();

            $this->subject->success($this->substrand1->mock(), '<one>');

            $this->strand->resume->calledWith(
                [
                    1 => '<two>',
                    0 => '<one>',
                ]
            );
        });

        it('resumes the strand with an exception when any substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->failure($this->substrand1->mock(), $exception->mock());

            $this->strand->throw->calledWith($exception);
        });

        it('resumes the strand with an exception when any substrand is terminated', function () {
            $this->subject->terminated($this->substrand1->mock());

            $this->strand->throw->calledWith(
                new TerminatedException($this->substrand1->mock())
            );
        });

        it('terminates unused substrands', function () {
            $this->subject->terminated($this->substrand1->mock());

            Phony::inOrder(
                $this->substrand2->setObserver->calledWith(null),
                $this->substrand2->terminate->called(),
                $this->strand->throw->called()
            );
        });
    });

    describe('->cancel()', function () {
        it('only terminates the pending substrands', function () {
            $this->subject->success($this->substrand1->mock(), '<one>');

            $this->subject->cancel();

            $this->substrand1->setObserver->never()->calledWith(null);
            $this->substrand1->terminate->never()->called();

            Phony::inOrder(
                $this->substrand2->setObserver->calledWith(null),
                $this->substrand2->terminate->called()
            );
        });
    });

});