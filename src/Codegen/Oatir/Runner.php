<?php

declare( strict_types = 1 );

namespace Codegen\Oatir;

class Runner {
   /** @var Instruction[] */
   private array $instructions;
   /** @var Slot[] */
   private array $slots;
   /** @var Func[] */
   private array $funcs;
   private Archive $archive;
   /** @var Block[] */
   private array $blocks;
   private Block $activeBlock;

   public function __construct( Archive $archive ) {
      $this->instructions = [];
      $this->slots = [];
      $this->funcs = [];
      $this->archive = $archive;
      $this->blocks = [];
      $this->addBlock();
   }

   public function appendInstruction( Instruction $instruction ): void {
      array_push( $this->activeBlock->instructions, $instruction );
   }

   public function add( int $opcode ): Instruction {
      $instruction = new Instruction( $opcode );
      array_push( $this->activeBlock->instructions, $instruction );
      return $instruction;
   }

   public function addImmediateArg( Instruction $instruction,
      int $value ): void {
      $arg = new InstructionArg( ARG_IMM, $value );
      array_push( $instruction->args, $arg );
   }

   private function addNewSlotArg( Instruction $instruction ): Slot {
      $slot = $this->allocSlot();
      $arg = new InstructionArg( ARG_SLOT, $slot );
      array_push( $instruction->args, $arg );
      return $slot;
   }

   public function addSlotArg( Instruction $instruction, Slot $slot ): void {
      $arg = new InstructionArg( ARG_SLOT, $slot );
      array_push( $instruction->args, $arg );
   }

   public function addFuncArg( Instruction $instruction, Func $func ): void {
      $arg = new InstructionArg( ARG_FUNC, $func );
      array_push( $instruction->args, $arg );
   }

   public function addS( int $opcode, Slot $arg1 ): void {
      $instruction = $this->add( $opcode );
      $this->addSlotArg( $instruction, $arg1 );
   }

   public function setImm( int $value ): Slot {
      $instruction = new SetImmInstruction();
      $instruction->value = $value;
      $instruction->destination = $this->allocSlot();
      $instruction->destination->type = TYPE_ISIZE;
      $this->appendInstruction( $instruction );
      return $instruction->destination;
   }

   public function allocSlot(): Slot {
      $id = count( $this->slots );
      $slot = new Slot();
      $slot->id = $id;
      array_push( $this->slots, $slot );
      return $slot;
   }

   public function addBlock(): Block {
      $block = new Block();
      $block->id = count( $this->blocks );
      array_push( $this->blocks, $block );
      $this->activeBlock = $block;
      return $block;
   }

   public function jumpToBlock( Block $src, Block $dst ): void {
      $jump = new Jump();
      $jump->dst = $dst;
      $src->exitJump = $jump;
   }

   public function condJumpToBlock( Slot $cond,
      Block $src, Block $onTrue, Block $onFalse ): void {
      $jump = new Jump();
      $jump->onTrue = $onTrue;
      $jump->onFalse = $onFalse;
      $jump->cond = $cond;
      $src->exitJump = $jump;
   }

   public function changeBlock( Block $block ): void {
      $this->activeBlock = $block;
   }

   public function finalize(): array {
      return $this->blocks;
   }

   public function findFunc( \Func $func ): Func {
      foreach ( $this->archive->funcs as $irFunc ) {
         if ( $irFunc->func === $func ) {
            return $irFunc;
         }
      }
      UNREACHABLE();
   }
}
