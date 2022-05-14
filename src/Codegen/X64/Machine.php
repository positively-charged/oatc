<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class Section {
   public Block $block;
}

class Machine {
   /** @var Slot[] */
   private array $slots;
   /** @var Value[] */
   private array $values;
   private Assembly $assembly;

   public function __construct( Assembly $assembly,
      private Sequence $seq ) {
      $this->slots = [];
      $this->values = [];
      $this->assembly = $assembly;
   }

   public function add( Value $dst, Value $src ): Value {
      $result = $this->allocValue();
      $instruction = new AddInstruction();
      $instruction->dst = $dst;
      $instruction->src = $src;
      $instruction->result = $result;
      //$instruction->dst = $this->registerFile->replace( $dst, $value );
      //$instruction->src = $this->registerFile->moveToReg( $src );
      $this->seq->appendInstruction( $instruction );
      $this->seq->activeBlock->addValueUsage( $dst );
      $this->seq->activeBlock->addValueUsage( $src );
      $this->seq->activeBlock->defineValue( $result );
      ++$dst->usages;
      ++$src->usages;
      //$this->drop( $dst );
      //$this->drop( $src );
      return $result;
   }

   public function idiv( Value $dividend, Value $divisor ): Value {
      $this->registerFile->store( REG_RAX, $dividend );
      $this->registerFile->store( REG_RDX, $this->newImm( 0 ) );
      $instruction = new IdivInstruction();
      $instruction->divisor = $this->registerFile->moveToReg( $divisor );
      $this->seq->appendInstruction( $instruction );
      $value = $this->allocValue();
      $value->residence = RESIDENCE_REG;
      $this->registerFile->freeRegister( REG_RAX );
      $this->registerFile->freeRegister( REG_RDX );
      return $value;
   }

   private function newImm( int $immediate ): Value {
      $value = $this->allocValue();
      $value->immediate = $immediate;
      return $value;
   }

   public function movImm( int $immediate ): Value {
      $dst = $this->allocValue();
      $value = $this->allocValue();
      $value->immediate = $immediate;
      $instruction = new MovInstruction();
      $instruction->src = $value;
      $instruction->dst = $dst;
      $this->seq->appendInstruction( $instruction );
      //$this->registerFile->store( $this->registerFile->allocRegister()->id,
      //   $value );
      return $value;
   }

   public function movSlot( Slot $dst, Value $value ): void {
      $instruction = new MovSlotInstruction();
      $instruction->dst = $dst;
      $instruction->value = $value;
      $this->seq->appendInstruction( $instruction );
   }

   public function cmp( Value $lside, Value $rside ): void {
      $instruction = new CmpInstruction();
      $instruction->lside = $lside;
      $instruction->rside = $rside;
      $this->seq->appendInstruction( $instruction );

      $this->seq->activeBlock->addValueUsage( $lside );
      $this->seq->activeBlock->addValueUsage( $rside );
   }

   /**
    * Retrieves the Z flag.
    */
   public function setz(): Value {
      //$value->residence = RESIDENCE_REG;
      //$value->register = $this->registerFile->allocRegister();
      //$value->reg = $value->register->getLowerHalf();
      $instruction = new SetInstruction( SetInstruction::SET_Z );
      $instruction->result = $this->allocValue();
      $this->seq->appendInstruction( $instruction );
      $this->seq->activeBlock->defineValue( $instruction->result );
      return $instruction->result;
   }

   /**
    * Returns 1 if Zero flag is NOT set, 0 otherwise.
    */
   public function setnz(): Value {
      $instruction = new SetInstruction( SetInstruction::SET_NZ );
      $instruction->result = $this->allocValue();
      $this->seq->appendInstruction( $instruction );
      $this->seq->activeBlock->defineValue( $instruction->result );
      return $instruction->result;
   }

   /**
    * Returns 1 if Sign flag is set, 0 otherwise.
    */
   public function setl(): Value {
      $instruction = new SetInstruction( SetInstruction::SET_L );
      $instruction->result = $this->allocValue();
      $this->seq->appendInstruction( $instruction );
      $this->seq->activeBlock->defineValue( $instruction->result );
      return $instruction->result;
   }

   public function test( Value $lside, ?Value $rside = null ): void {
      if ( $rside === null ) {
         $rside = $lside;
      }
      $instruction = new TestInstruction();
      $instruction->lside = $lside;
      $instruction->rside = $rside;
      //$instruction->appendReg( $this->registerFile->moveToReg( $lside ) );
      //$instruction->appendReg( $this->registerFile->moveToReg( $rside ) );
      $this->seq->appendInstruction( $instruction );
   }

   public function getReturnValue( Value $value ): void {
      $instruction = new MovRetInstruction();
      $instruction->value = $value;
      $this->seq->appendInstruction( $instruction );
   }

   public function evictReturnValue(): void {
      $this->registerFile->evictReg( REG_RAX );
   }

   public function allocValue(): Value {
      $id = count( $this->values );
      $value = new Value();
      $value->refCount = 1;
      $value->usages = 0;
      $value->id = $id;
      $this->values[ $id ] = $value;
      return $value;
   }

   public function drop( Value $value ): void {
      if ( array_key_exists( $value->id, $this->values ) ) {
         --$value->refCount;
         if ( $value->refCount <= 0 ) {
            if ( $value->residence === RESIDENCE_REG ) {
               $this->registerFile->freeRegister( $value->reg );
            }
            unset( $this->values[ $value->id ] );
         }
      }
   }

   public function allocSlot(): Slot {
      $id = count( $this->slots );
      $slot = new Slot();
      $slot->id = $id;
      array_push( $this->slots, $slot );
      return $slot;
   }

   public function createBlock(): Block {
      return $this->seq->createBlock();
   }

   public function setActiveBlock( Block $block ): void {
      $this->activeBlock = $block;
   }

   public function setBlockJump( Jump $jump ): void {
      $this->activeBlock->exitJump = $jump;
   }

   public function jmp( Section $destination ): void {
     // if ( $this->seq->activeBlock->exitJump === null ) {
     static $a = 0;
     ++$a;
     //if ( $a === 2 ) throw new \Exception();
         $jump = new GotoJump();
         $jump->dst = $destination->block;
         $this->seq->activeBlock->exitJump = $jump;
         $destination->block->addParent( $this->seq->activeBlock );
         $this->seq->activeBlock->addChild( $destination->block );
      //}
   }

   public function jmpz( Section $destination ): void {
     // if ( $this->seq->activeBlock->exitJump === null ) {
         $jump = new ConditionalJump( COND_JUMP_ZERO,
            $destination->block );
         $this->seq->activeBlock->exitJump = $jump;
      $destination->block->addParent( $this->seq->activeBlock );
      $this->seq->activeBlock->addChild( $this->seq->activeBlock->next );
      $this->seq->activeBlock->addChild( $destination->block );
      //}
   }

   public function jmpnz( Section $destination ): void {
     // if ( $this->seq->activeBlock->exitJump === null ) {
         $jump = new ConditionalJump( COND_JUMP_NOT_ZERO,
            $destination->block );
         $this->seq->activeBlock->exitJump = $jump;
      $destination->block->addParent( $this->seq->activeBlock );
      $this->seq->activeBlock->addChild( $this->seq->activeBlock->next );
      $this->seq->activeBlock->addChild( $destination->block );
      //}
   }

   /**
    * @param Value[] $args
    */
   public function call( Func $func, array $args ): Value {
      $instruction = new CallInstruction();
      $instruction->func = $func;
      foreach ( $args as $arg ) {
         $instruction->args[] = $arg;
      }
      $this->seq->appendInstruction( $instruction );
      $returnValue = $this->allocValue();
      $instruction->returnValue = $returnValue;
      return $returnValue;
   }

   public function separate( string $name = '' ): Section {
      $block = $this->seq->addBlock();
      if ( $name !== '' ) {
         $block->comment = sprintf( '%s section', $name );
      }
      $section = new Section();
      $section->block = $block;
      return $section;
   /*
      $block = $this->seq->createBlock();
      $jump = new GotoJump();
      $jump->dst = $block;
      $this->seq->activeBlock->exitJump = $jump;
      $this->seq->activeBlock = $block;
      $section = new Section();
      $section->block = $block;
      return $section;
   */
   }

   public function enter( Section $section ): void {
      $this->seq->activeBlock = $section->block;
   }

   public function finalize(): array {
      $prevBlock = null;
      $block = reset( $this->seq->blocks );
      //for ( $i = 0; $i < count( $this->seq->blocks ); ++$i ) {
      while ( $block !== null ) {
         $nextBlock = $block->next;

         // Parent.
         if ( $prevBlock === null && $block->exitJump === null && $nextBlock !== null ) {
            $nextBlock->addParent( $block );
         }
         else if ( $nextBlock === null ) {
            $block->addParent( $prevBlock );
         }

         // Child.
         if ( $block->exitJump === null && $nextBlock !== null ) {
            $jump = new GotoJump();
            $jump->dst = $nextBlock;
            $block->addChild( $nextBlock );
            $block->exitJump = $jump;
         }

         $prevBlock = $block;
         $block = $nextBlock;
      }

/*
      $dumper = new BlockDumper( $this->seq->blocks );
      $dumper->showDefs();
      $dumper->showLiveValues();
      $dumper->showChildBlocks();
      $dumper->showParentBlocks();
      $dumper->dump();

      // Propogate values used in subsequent blocks to their parent blocks.
      // With this, blocks can know which values can still be used. If a value
      // is defined in the current block, there is no need to propogate it to
      // its parent blocks because a parent block does not need to know about
      // a value that does not yet exist.

      $numDone = 0;
      while ( $numDone < count( $this->seq->blocks ) ) {
         foreach ( $this->seq->blocks as $block ) {
            if ( ! $block->valueUsageDetermined &&
               $block->descendentsUsageDetermined() ) {
               foreach ( $block->values as $value ) {
                  $block->propogateValueUsage( $value );
               }
               $dumper = new BlockDumper( [] );
               $content = new Content();
               $dumper->dumpBlockLabel( $content, $block );
               printf( "- %s\n", $content->output );
               $block->valueUsageDetermined = true;
               ++$numDone;
            }
         }
      } */

/*
      $queue = [ end( $this->seq->blocks ) ];
      while ( ! empty( $queue ) ) {
         $block = array_pop( $queue );
         foreach ( $block->values as $value ) {
            $block->propogateValueUsage( $value );
         }
         $block->valueUsageDetermined = true;
         foreach ( $block->parents as $parent ) {
            if ( ! $parent->valueUsageDetermined ) {
               array_push( $queue, $parent );
            }
         }
      } */

      foreach ( $this->seq->blocks as $block ) {
         //var_dump( $block->liveValues );
      }

      $dumper = new BlockDumper( $this->seq->blocks );
      $dumper->showDefs();
      $dumper->showLiveValues();
      $dumper->showChildBlocks();
      $dumper->showParentBlocks();
      $dumper->dump();
      //exit(1 );


   /*
      $this->markUsefulSlots();
      $this->markUsefulInstructions();
      foreach ( $this->blocks as $block ) {
         if ( $block->exitJump !== null ) {
            if ( $block->exitJump instanceof GotoJump ) {
               $block->exitJump->dst->enteredViaJump = true;
            }
         }
      }
   */
      return $this->seq->finalize();
   }

   private function markUsefulSlots(): void {
      foreach ( $this->slots as $slot ) {
         if ( $slot->critical ) {
            $this->markUsefulSlot( $slot );
         }
      }
   }

   private function markUsefulSlot( Slot $slot ): void {
      if ( ! $slot->useful ) {
         $slot->useful = true;
         foreach ( $slot->dependencies as $dependency ) {
            $this->markUsefulSlot( $dependency );
         }
      }
   }

   private function markUsefulInstructions(): void {
      foreach ( $this->blocks as $block ) {
         foreach ( $block->instructions as $instruction ) {
            if ( $instruction instanceof BinaryInstruction ) {
               $instruction->useful =
                  $instruction->lside->useful ||
                  $instruction->rside->useful;
            }
            else if ( $instruction instanceof RetInstruction ||
               $instruction instanceof MovInstruction ||
               $instruction instanceof SetSlotInstruction ||
               $instruction instanceof DestroyInstruction ) {

            }
            else if ( $instruction instanceof CallInstruction ) {
               $instruction->useful = true;
            }
            else {
               UNREACHABLE();
            }
         }
      }
   }

   public function findFunc( \Func $func ): Func {
      foreach ( $this->assembly->funcs as $irFunc ) {
         if ( $irFunc->func === $func ) {
            return $irFunc;
         }
      }
      UNREACHABLE();
   }

   public function isFlowDead(): bool {
      return ( $this->activeBlock->exitJump !== null );
      return false;
   }

   public function nop(): void {
      $this->seq->nop();
   }

   public function reserveSpace(): void {
      $this->registerFile->reserve();
   }

   public function flushRegisters(): void {
      $this->registerFile->flush();
   }
}
