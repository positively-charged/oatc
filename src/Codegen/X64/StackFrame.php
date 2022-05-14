<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class StackFrame {
   /** @var Allocation[] */
   private array $localSpace;
   private Func $func;

   public function __construct( Func $func ) {
      $this->localSpace = [];
      $this->func = $func;
   }

   /**
    * Allocates a block of space for the slot on the stack frame.
    */
   public function alloc( Value $value ): Allocation {
      if ( ! empty( $this->freeAllocs ) ) {
         $alloc = array_pop( $this->freeAllocs );
      }
      else {
         $alloc = new Allocation();
         $alloc->offset = $this->func->localSize;
         $this->func->localSize += 8;
         array_push( $this->localSpace, $alloc );
      }
      $alloc->value = $value;
      //$slot->allocated = true;
      return $alloc;
      /*
         $alloc = $this->findFreeAlloc( $slot );
         if ( $alloc !== null ) {
            $alloc->slot = $slot;
            return $alloc;
         }
         else {
            $alloc = new Allocation();
            $alloc->slot = $slot;
            $alloc->offset = $this->func->localSize;
            $alloc->size = 8;
            array_push( $this->localSpace, $alloc );
         }*/
   }

   public function free( Allocation $alloc ): void {

   }
}
