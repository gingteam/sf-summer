<?php

namespace App\StaticAnalysis;

/**
 * @phpstan-type Ulogin array{uid:string,first_name:string,last_name:string}
 */
class Type
{
    private function __construct(private readonly mixed $type)
    {
    }

    public static function from(mixed $data): self
    {
        return new self($data);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function to(string $class)
    {
        return $this->type;
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function list(string $class)
    {
        return $this->type;
    }
}
