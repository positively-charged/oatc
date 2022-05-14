<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class InstructionIterator {
   public Block $activeBlock;
   public ?Instruction $prev;
   public Instruction $instruction;
   public array $instructions;
   public int $seen;

   private Func $func;
   private int $block;
   private int $entry;

   public function __construct( Func $func ) {
      $this->func = $func;
      $this->instructions = [];
      $this->block = 0;
      $this->entry = 0;
      $this->seen = 0;
   }

   public function nextBlock(): bool {
      if ( $this->block < count( $this->func->blocks ) ) {
         $this->activeBlock = $this->func->blocks[ $this->block ];
         $this->instructions = $this->activeBlock->instructions;
         $this->activeBlock->instructions = [];
         ++$this->block;
         $this->entry = 0;
         return true;
      }
      else {
         return false;
      }
   }

   public function nextInstruction(): bool {
      if ( $this->entry < count( $this->instructions ) ) {
         $this->instruction = $this->instructions[ $this->entry ];
         ++$this->entry;
         ++$this->seen;
         return true;
      }
      else {
         return false;
      }
   }

   public function next(): bool {
      if ( $this->block < count( $this->func->blocks ) ) {
         if ( $this->entry < count( $this->func->blocks[
            $this->block ]->instructions ) ) {
            $this->instruction = $this->func->blocks[
               $this->block ]->instructions[ $this->entry ];
            ++$this->entry;
            ++$this->seen;
            return true;
         }
         else {
            ++$this->block;
            $this->entry = 0;
            return $this->next();
         }
      }
      else {
         return false;
      }
   }

   public function insertBefore( Instruction $instruction ): void {
      // Minus one to go to the current instruction.
      $prevEntry = $this->entry - 1;
      if ( $prevEntry >= 0 ) {
         #$block = $this->func->blocks[ $this->block ];
         $block = $this->activeBlock;
         array_splice( $block->instructions, $prevEntry, 0, [ $instruction ] );
         ++$this->entry;
      }
      else {
         array_push( $this->activeBlock->instructions, $instruction );
         ++$this->entry;
      }
   }

   public function appendInstruction( Instruction $instruction ): void {
      array_push( $this->activeBlock->instructions, $instruction );
      //++$this->totalInstructions;
   }
}
