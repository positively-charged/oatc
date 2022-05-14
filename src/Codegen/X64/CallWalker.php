<?php

declare( strict_types = 1 );

namespace Codegen\X64;

use \Codegen\Oatir;

class CallWalker {
   private Oatir\CallInstruction $instruction;
   public int $intArgsAdded;

   public function __construct( Oatir\CallInstruction $instruction ) {
      $this->instruction = $instruction;
      $this->intArgsAdded = 0;
   }

   public function visit( Sequence $seq ): void {
      $this->passArgs( $seq );
      $seq->addM( OP_CALL, $this->instruction->func->name );
      if ( $this->instruction->returnValue !== null ) {
         $seq->allocConcreteReg( $this->instruction->returnValue, REG_RAX );
      }
   }

   private function passArgs( Sequence $seq ): void {
      foreach ( $this->instruction->args as $arg ) {
         switch ( $arg->type ) {
         case Oatir\TYPE_ISIZE:
            $this->passIntArg( $seq, $arg );
            break;
         default:
            UNREACHABLE();
         }
      }
      if ( $this->instruction->func->variadic ) {
         $seq->addRI( OP_MOV_R64I64, REG_RAX, 0 );
      }
   }

   private function passIntArg( Sequence $seq, Oatir\Slot $arg ): void {
      $registers = [ REG_RDI, REG_RSI, REG_RDX, REG_RCX, REG_R8, REG_R9 ];
      // Pass argument in register.
      if ( $this->intArgsAdded < count( $registers ) ) {
         $reg = $registers[ $this->intArgsAdded ];
         ++$this->intArgsAdded;
         $argReg = $seq->getReg( $arg );
         $seq->moveToReg( $argReg, $reg );
      }
      // Pass argument on the stack.
      else {

      }
   }
}
