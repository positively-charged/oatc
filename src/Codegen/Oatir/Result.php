<?php

declare( strict_types = 1 );

namespace Codegen\OatIr;

class Result {
   public PhpNode $node;
   public bool $method = false;
   public ?\Structure $structure = null;
   public ?\StructureMember $member = null;
   public ?\Enumerator $enumerator = null;
   public ?\TraitObj $trait = null;
   public ?PhpFunc $func = null;
   public ?PhpVar $returnValue = null;
   public ?Slot $slot = null;
}
