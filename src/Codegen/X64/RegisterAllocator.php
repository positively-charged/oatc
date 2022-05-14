<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class Allocation {
   /** The slot that is currently occupying the allocation. */
   public ?Value $value = null;
   /** Start of the allocation within the local space */
   public int $offset;
   /** The size of the allocation in bytes. */
   public int $size;
}

class RegisterAllocator {
   private Assembly $assembly;
   /** @var Register[] */
   private array $registers;
   private Func $func;
   /** @var Allocation[] */
   private array $localSpace;
   private RegisterFile $registerFile;

   public function __construct( Assembly $assembly ) {
      $this->assembly = $assembly;
      $this->registers = [];
      $this->localSpace = [];
      $this->allocRegisters();
   }

   private function allocRegisters(): void {
      for ( $id = 0; $id < REG_TOTAL; ++$id ) {
         if ( $id !== REG_RBP && $id !== REG_RSP ) {
            $reg = new Register( $id );
            $this->registers[ $id ] = $reg;
         }
      }
   }

   public function allocate(): void {
      foreach ( $this->assembly->funcs as $func ) {
         $this->visitFunc( $func );
      }
   }

   private function visitFunc( Func $func ): void {
      $stackFrame = new StackFrame( $func );

      $this->func = $func;
      $this->localSpace = [];
      $this->allocRegisters();

      $registersPos = 0;
      $registers = [ REG_RDI, REG_RSI, REG_RDX, REG_RCX, REG_R8, REG_R9 ];
      $i = 0;
      foreach ( $func->params as $param ) {
         if ( $registersPos < count( $registers ) ) {
            $alloc = $this->allocSpace( $param->slot );
            $param->slot->offset = $alloc->offset;
            $param->reg = $registers[ $registersPos ];
            ++$registersPos;
         }
         else {
            $param->slot->offset = $i * 8;
            $param->argOnStack = true;
            ++$i;
         }
      }

      $iter = new InstructionIterator( $func );
      $this->registerFile = new RegisterFile( $iter, $stackFrame );
      while ( $iter->nextBlock() ) {
         while ( $iter->nextInstruction() ) {
            $this->visitInstruction( $iter );
         }
         $this->registerFile->flush( $iter->activeBlock );
         //$this->visitJump( $iter );
      }
      /*
      while ( $iter->next() ) {
         $this->visitInstruction( $iter );
            var_dump(1 );
      } */
   }

   private function visitInstruction( InstructionIterator $iter ): void {
      if ( $iter->instruction instanceof MovInstruction ) {
         $this->visitMovInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof BinaryInstruction ) {
         $this->visitBinaryInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof RetInstruction ) {
         $this->visitRetInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof CallInstruction ) {
         $this->visitCallInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof SetSlotInstruction ) {
         $this->visitSetSlotInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof DestroyInstruction ) {
         $this->visitDestroyInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof AddInstruction ) {
         $this->visitAddInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof CmpInstruction ) {
         $this->visitCmpInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof SetInstruction ) {
         $this->visitSetInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof TestInstruction ) {
         $this->visitTestInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof MovSlotInstruction ) {
         $this->visitMovSlotInstruction( $iter, $iter->instruction );
      }
      else if ( $iter->instruction instanceof MovRetInstruction ) {
         $this->visitMovRetInstruction( $iter, $iter->instruction );
      }
      else {
      var_dump( $iter->instruction );
         UNREACHABLE();
      }
   }

   private function visitMovRetInstruction( InstructionIterator $iter,
      MovRetInstruction $mov ): void {
      $reg = $this->registerFile->moveToReg( $mov->value );
      /*
      $rside = $this->registerFile->moveToReg( $test->rside );
      $instruction = new Instruction( OP_TEST );
      $instruction->appendReg( $lside );
      $instruction->appendReg( $rside );
      $iter->appendInstruction( $instruction ); */
   }

   private function visitMovSlotInstruction( InstructionIterator $iter,
      MovSlotInstruction $mov ): void {
      $reg = $this->registerFile->moveToReg( $mov->value );
      /*
      $rside = $this->registerFile->moveToReg( $test->rside );
      $instruction = new Instruction( OP_TEST );
      $instruction->appendReg( $lside );
      $instruction->appendReg( $rside );
      $iter->appendInstruction( $instruction ); */
   }

   private function visitTestInstruction( InstructionIterator $iter,
      TestInstruction $test ): void {
      $lside = $this->registerFile->moveToReg( $test->lside );
      $rside = $this->registerFile->moveToReg( $test->rside );
      $instruction = new Instruction( OP_TEST );
      $instruction->appendReg( $lside );
      $instruction->appendReg( $rside );
      $iter->appendInstruction( $instruction );
   }

   private function visitSetInstruction( InstructionIterator $iter,
      SetInstruction $set ): void {
      $set->result->residence = RESIDENCE_REG;
      $set->result->register = $this->registerFile->allocRegister();
      $dstReg = $set->result->register->getLowerHalf();
      $opcode = match ( $set->type ) {
         SetInstruction::SET_Z => OP_SETZ_R8,
         SetInstruction::SET_NZ => OP_SETNZ_R8,
         SetInstruction::SET_L => OP_SETL_R8,
      };
      $instruction = new Instruction( $opcode );
      $instruction->appendReg( $dstReg );
      $iter->appendInstruction( $instruction );

      // Zero out the rest of the register.
      $instruction = new Instruction( OP_MOVZX_R64R8 );
      $instruction->appendReg( $set->result->register->id );
      $instruction->appendReg( $dstReg );
      $iter->appendInstruction( $instruction );
   }

   private function visitCmpInstruction( InstructionIterator $iter,
      CmpInstruction $cmp ): void {
      $lside = $this->registerFile->moveToReg( $cmp->lside );
      $rside = $this->registerFile->moveToReg( $cmp->rside );
      $instruction = new Instruction( OP_CMP_R64R64 );
      $instruction->appendReg( $lside );
      $instruction->appendReg( $rside );
      $iter->appendInstruction( $instruction );
      $this->drop( $cmp->lside );
      $this->drop( $cmp->rside );
   }

   private function visitAddInstruction( InstructionIterator $iter,
      AddInstruction $add ): void {
      $instruction = new Instruction( OP_ADD_R64R64 );
      $this->registerFile->moveToReg( $add->dst );
      $dst = $this->registerFile->replace( $add->dst, $add->result );
      $instruction->appendReg( $dst );
      $instruction->appendReg( $this->registerFile->moveToReg( $add->src ) );
      $iter->appendInstruction( $instruction );
      $this->drop( $add->dst );
      $this->drop( $add->src );
   }

   public function drop( Value $value ): void {
      --$value->usages;
      if ( $value->usages <= 0 ) {
         if ( $value->residence === RESIDENCE_REG ) {
            $this->registerFile->freeRegister( $value->reg );
         }
      }
   }

   private function visitMovInstruction( InstructionIterator $iter,
      MovInstruction $instruction ): void {
      switch ( $instruction->type ) {
      case MOV_IMM_TO_REG:
         if ( is_int( $instruction->dst ) ) {
            $this->registerFile->store( $instruction->dst, $instruction->src );
         }
         else {
            $this->registerFile->moveToReg( $instruction->src );
         }
         break;
      case MOV_MEM_TO_REG:
         $instruction->dstReg = $this->allocReg( $instruction->dst );
         break;
      }
   }

   private function visitSetSlotInstruction( InstructionIterator $iter,
      SetSlotInstruction $instruction ): void {
      $instruction->sourceReg = $this->allocReg( $instruction->source );
      $alloc = $this->allocSpace( $instruction->destination );
      $instruction->destination->offset = $alloc->offset;
      $this->freeReg( $instruction->sourceReg );
      //$instruction->destinationReg = $this->moveToReg( $iter,
      //   $instruction->destination );
   }

   private function visitDestroyInstruction( InstructionIterator $iter,
      DestroyInstruction $instruction ): void {
      foreach ( $this->registers as $reg ) {
         if ( $reg->virtualRegister === $instruction->reg ) {
            $this->freeReg( $reg->id );
         }
      }
      //$instruction->destinationReg = $this->moveToReg( $iter,
      //   $instruction->destination );
   }

   private function visitBinaryInstruction( InstructionIterator $iter,
      BinaryInstruction $instruction ): void {
      switch ( $instruction->opcode ) {
      case OP_ADD:
         $instruction->lsideReg = $this->allocReg(
            $instruction->lside, true );
         $instruction->rsideReg = $this->allocReg(
            $instruction->rside );
         $this->freeReg( $instruction->rsideReg );
         //$this->destroySlot( $iter, $instruction->lside );
         #$this->freeReg( $instruction->rsideReg );
         #$this->releaseReg( $instruction->lsideReg );
         break;
      case OP_SUB:
         $result = $seq->allocReg( $instruction->result );
         $lside = $seq->getReg( $instruction->lside );
         $seq->addRR( OP_MOV_R64R64, $result, $lside );
         $rside = $seq->getReg( $instruction->rside );
         $seq->addRR( OP_SUB_RR, $result, $rside );
         break;
      default:
         UNREACHABLE();
      }
      //$this->destroySlot( $iter, $instruction->lside );
      //$this->destroySlot( $iter, $instruction->rside );
   }

   private function visitRetInstruction( InstructionIterator $iter,
      RetInstruction $instruction ): void {
      if ( $instruction->value !== null ) {
         $this->allocReg( $instruction->value );
         //$this->moveToReg( $iter, $iter->instruction->value );
      }
   }

   private function visitCallInstruction( InstructionIterator $iter,
      CallInstruction $instruction ): void {
      // The return value will be passed into a register. Free this register
      // of any value so the value does not get overwritten.
      if ( $instruction->func->returnsValue ) {
         var_dump( $instruction->func->name );
      }

      $registersPos = 0;
      $registers = [ REG_RDI, REG_RSI, REG_RDX, REG_RCX, REG_R8, REG_R9 ];
      for ( $i = 0; $i < count( $instruction->args ) &&
         $registersPos < count( $registers ); ++$i ) {
         $arg = $instruction->args[ $i ];
         $reg = $this->reserveReg( $iter, $registers[ $registersPos ] );
         $this->moveTo( $iter, $reg, $arg );
         ++$registersPos;
      }

      if ( $i < count( $instruction->args ) ) {
         //a$remainingArgs = count( $instruction->args ) - $i;
         while ( $i < count( $instruction->args ) ) {
            //sprintf( "mov [rsp+%d], ", ( $k + 1 ) * 8 );
            $push = new PushInstruction();
            $push->reg = $instruction->args[ $i ];
            $iter->insertBefore( $push );
            ++$i;
         }
      }

      if ( $instruction->returnValue !== null ) {
         $this->registerFile->materialize( $instruction->returnValue );
      }

      $iter->appendInstruction( $instruction );
   }

   private function visitJump( InstructionIterator $iter ): void {
      if ( $iter->activeBlock->exitJump instanceof ConditionalJump ) {
         //$iter->activeBlock->exitJump->condReg =
         //   $this->registerFile->moveToReg( $iter->activeBlock->exitJump->cond );
      }
   }

   /**
    * Allocates a block of space for the slot on the stack frame.
    */
   private function allocSpace(): Allocation {
      if ( ! empty( $this->freeAllocs ) ) {
         $alloc = array_pop( $this->freeAllocs );
      }
      else {
         $alloc = new Allocation();
         $alloc->offset = $this->func->localSize;
         $this->func->localSize += 8;
         array_push( $this->localSpace, $alloc );
      }
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

   private function freeAlloc( Slot $slot ): ?Allocation {
      foreach ( $this->localSpace as $alloc ) {
         if ( $alloc->slot === null ) {
            return $alloc;
         }
      }
      return null;
   }

   /**
    * When a slot is no longer used, free up resources used by the slot.
    */
   private function destroySlot( InstructionIterator $iter,
      Slot $slot ): void {
      if ( $slot->lastSeen < $iter->seen ) {
         foreach ( $this->registers as $reg ) {
            if ( $reg->slot === $slot ) {
               $reg->slot = null;
               $reg->immovable = false;
               $reg->used = false;
               break;
            }
         }
        # $this->destroySlot( $slot );
      }
   }
}
