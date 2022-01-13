<?php


namespace MaxBrokman\SafeQueue;

use Doctrine\Persistence\ObjectManager;
use Exception;
use Throwable;

class EntityManagerClosedException extends Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }


    public static function fromManager($name, ObjectManager $manager): self {
        return new self(sprintf("Object manager %s is closed (class: %s)", $name, get_class($manager)));
    }
}
