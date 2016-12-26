<?php
namespace Altair\Http\Contracts;


interface CsrfTokenInterface
{
    public function getToken(): string;
    public function getUri(): string;
}