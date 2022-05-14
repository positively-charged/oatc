<?php

declare( strict_types = 1 );

namespace Codegen\C;

use Codegen\C\CVar;

const RUNTIMEVALUE_INT = 0;
const RUNTIMEVALUE_BOOL = 1;
const RUNTIMEVALUE_STR = 2;
const RUNTIMEVALUE_VOID = 3;

class Result {
   public string $code;
   public string $var;
   public string $binding;
   public int $type;
   public ?CVar $cVar;
   public ?CFunc $cFunc;

   public function __construct() {
      $this->var = '';
      $this->type = \Codegen\C\RUNTIMEVALUE_INT;
      $this->binding = '';
      $this->cVar = null;
      $this->cFunc = null;
   }
}
