<?php

declare( strict_types = 1 );

namespace Checking;

use Typing\Type;

class CheckErr extends \Exception {
   public function __construct(
      public Value $result,
   ) {
      parent::__construct();
   }
}

class Value {
   public Type $type;
   public mixed $inhabitant;
   public bool $constant;
   public bool $evaluable;
   public bool $virtual;
   public bool $borrowed;
   public bool $mutableBinding;
   public bool $exposed;
   public bool $assigned;
   public ?\Binding $binding;
   public ?\Module $owner;
   public ?Type $container;
   public ?\StructureMember $member;
   public string $memberName;
   public string $name;
   public ?\Diagnostic $diag;

   public ?\Node $item;
   public ?\Func $func;
   public ?\Generic $generic;
   public bool $method;
   /** @var Value[] */
   public array $members;

   public ?\Structure $structure;
   public ?int $integer;
   public ?string $string;

   public function __construct() {
      $this->type = new Type();
      $this->inhabitant = null;
      $this->item = null;
      $this->func = null;
      $this->generic = null;
      $this->method = false;
      $this->constant = false;
      $this->evaluable = false;
      $this->virtual = false;
      $this->borrowed = false;
      $this->mutableBinding = false;
      $this->exposed = false;
      $this->assigned = false;
      $this->binding = null;
      $this->owner = null;
      $this->container = null;
      $this->member = null;
      $this->memberName = '';
      $this->name = '';
      $this->diag = null;
      $this->members = [];

      $this->structure = null;
      $this->integer = null;
      $this->string = null;
   }

   public function isInitialized(): bool {
      return ( $this->type->initialized );
   }

   public function expectValue( $value ): void {
      if ( is_int( $value ) ) {
       //  var_dump( $value );
       //  var_dump( $this );
      }
   }
}
