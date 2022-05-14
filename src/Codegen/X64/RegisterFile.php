<?php

declare( strict_types = 1 );

namespace Codegen\X64;

class RegisterFile {
   /** @var Register[] */
   private array $registers;

   public function __construct( private InstructionIterator $iter,
      private StackFrame $stackFrame ) {
      $this->registers = [];
      for ( $id = 0; $id < REG_TOTAL; ++$id ) {
         $reg = new Register( $id );
         $this->registers[ $id ] = $reg;
      }
   }

   public function replace( Value $target, Value $replacement ): int {
      if ( $target->residence === RESIDENCE_REG ) {
         if ( $target->refCount > 1 ) {
            $this->evictReg( $target->register->id );
         }
      }
      else {
         if ( $target->register->containsValidValue() ) {
            if ( ( $target->register->value->refCount > 1 &&
               $target->register->value === $target ) ||
               ( $target->register->value->refCount > 0 &&
                  $target->register->value !== $target )
                ) {
               $this->evictReg( $target->register->id );
            }
         }
         switch ( $target->residence ) {
         case RESIDENCE_IMM:
            $mov = new MachineInstruction( OP_MOV_R64I64 );
            $mov->appendReg( $target->reg );
            $mov->appendImm( $target->immediate );
            $this->iter->appendInstruction( $mov );
            break;
         case RESIDENCE_MEM:
            $mov = new MachineInstruction( OP_MOV_R64M64 );
            $mov->appendReg( $target->reg );
            // $mov->appendImm( $target->immediate );
            $mov->appendMemory( $target->addr );
            if ( $target->name !== '' ) {
               $mov->comment = sprintf( "Copy `%s` to register",
                  $target->name );
            }
            else {
               $mov->comment = "Copy to register";
            }
            $this->iter->appendInstruction( $mov );
            break;
         case RESIDENCE_REG:
            break;
         }
      }

      $this->evictReg( $target->reg );

      $replacement->residence = RESIDENCE_REG;
      $replacement->reg = $target->reg;
      $replacement->register = $target->register;
      $target->register->store( $replacement );
      //$target->residence = RESIDENCE_DEAD;

      return $replacement->reg;
   }

   public function moveToReg( Value $value, int $regId = -1 ): int {
      if ( ! ( $value->residence === RESIDENCE_REG &&
         $value->reg === $regId ) ) {
         if ( $regId === -1 ) {
            if ( $value->residence === RESIDENCE_REG ) {
               return $value->reg;
            }
            $reg = $this->allocRegister();
         }
         else {
            $reg = $this->registers[ $regId ];
         }

         switch ( $value->residence ) {
         case RESIDENCE_IMM:
            $mov = new MachineInstruction( OP_MOV_R64I64 );
            $mov->appendReg( $reg->id );
            $mov->appendImm( $value->immediate );
            $this->iter->appendInstruction( $mov );
            break;
         case RESIDENCE_MEM:
            $mov = new MachineInstruction( OP_MOV_R64M64 );
            $mov->appendReg( $reg->id );
            // $mov->appendImm( $target->immediate );
            $mov->appendMemory( $value->addr );
            if ( $value->name !== '' ) {
               $mov->comment = sprintf( "Copy `%s` to register",
                  $value->name );
            }
            else {
               $mov->comment = "Copy to register";
            }
            $this->iter->appendInstruction( $mov );
            break;
         case RESIDENCE_REG:
            $mov = new MachineInstruction( OP_MOV_R64R64 );
            $mov->appendReg( $reg->id );
            $mov->appendReg( $value->register->id );
            $this->iter->appendInstruction( $mov );
         }

         $value->residence = RESIDENCE_REG;
         $value->reg = $reg->id;
         $value->register = $reg;
         $reg->value = $value;
      }
      return $value->reg;
   }

   public function allocRegister(): Register {
      $freeReg = null;
      foreach ( $this->registers as $reg ) {
         if ( ! in_array( $reg->id, [ REG_RBP, REG_RSP ] ) &&
            ! $reg->containsValidValue() ) {
            $freeReg = $reg;
            break;
         }
      }

      if ( $freeReg !== null ) {
         $freeReg->used = true;
         return $freeReg;
      }

      // No registers are free. Spill a register to memory.
      UNREACHABLE();
   }

   public function freeRegister( int $reg ): void {
      if ( array_key_exists( $reg, $this->registers ) ) {
         $this->registers[ $reg ]->value = null;
      }
   }

   public function evictReg( int $regId ): void {
      $reg = $this->registers[ $regId ];
      if ( $reg->containsValidValue() ) {
         if ( $reg->value?->slot?->allocation !== null ) {
            $alloc = $reg->value->slot->allocation;
         }
         else {
            $alloc = $this->stackFrame->alloc( $reg->value );
            if ( $reg->value->slot !== null ) {
               $reg->value->slot->allocation = $alloc;
            }
         }
         $mov = new MachineInstruction( OP_MOV_M64R64 );
         $mov->appendMemory( $alloc->offset );
         $mov->appendReg( $reg->id );
         if ( $reg->value->name !== '' ) {
            $mov->comment = sprintf( "Move `%s` to memory",
               $reg->value->name );
         }
         else {
            $mov->comment = "Move to memory";
         }
         $this->iter->appendInstruction( $mov );
         $reg->value->residence = RESIDENCE_MEM;
         $reg->value->addr = $alloc->offset;
         $reg->value = null;
      }
   }

   public function store( int $regId, Value $value ): void {
      // Value already in the register.
      if ( $value->residence === RESIDENCE_REG &&
         $value->reg === $regId ) {
         return;
      }

      $this->evictReg( $regId );
      $this->moveToReg( $value, $regId );
   }

   public function materialize( Value $value ): void {
      $reg = $this->allocRegister();
      $reg->value = $value;
      $reg->value->residence = RESIDENCE_REG;
      $reg->value->register = $reg;
      $reg->value->reg = $reg->id;
   }

   public function flush( Block $block ): void {
      foreach ( $this->registers as $reg ) {
         if ( $reg->value !== null ) {
            //in_array( $reg->value, $block->liveValues ) ) {
            $this->evictReg( $reg->id );
         }
      }
   }
}
