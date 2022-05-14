<?php

declare( strict_types = 1 );

namespace Codegen\X64;

const FLOW_GOING = 0;
const FLOW_DEAD = 1;

const RESIDENCE_REG = 0;
const RESIDENCE_MEM = 1;
const RESIDENCE_IMM = 2;
const RESIDENCE_DEAD = 3;

class Value {
   public int $id;
   public ?Register $register = null;
   public ?Slot $slot = null;
   public int $refCount = 0;
   public int $usages = 0;
   public string $name = '';
   public bool $used = false;
   public ?Allocation $reservedSpace = null;
   public function __construct(
      public int $residence = RESIDENCE_IMM,
      public int $immediate = 0,
      public int $reg = REG_RAX,
      public int $addr = 0,
   ) {}
}

class Result {
   public ?VirtualRegister $reg = null;
   public PhpNode $node;
   public bool $method = false;
   public bool $folded = false;
   public bool $returning = false;
   public ?\Structure $bundle = null;
   public ?\StructureMember $member = null;
   public ?\Enumerator $enumerator = null;
   public ?\TraitObj $trait = null;
   public ?PhpFunc $func = null;
   public ?PhpVar $returnValue = null;
   public ?Slot $slot = null;
   public int $flow = FLOW_GOING;
   public ?Value $value = null;
}
