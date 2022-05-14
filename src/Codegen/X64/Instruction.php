<?php

declare( strict_types = 1 );

namespace Codegen\X64;

/**
 * Represents an x86-64 instruction.
 */ 
class Instruction {
   public int $opcode;
   /** @var InstructionArg[] */
   public array $args;
   public string $comment;
   public bool $useful;

   public function __construct( int $opcode ) {
      $this->opcode = $opcode;
      $this->args = [];
      $this->comment = '';
      $this->useful = false;
   }

   public function dump( bool $debug = false ): string {
      $output = '';
      switch ( $this->opcode ) {
      case OP_NOP:
         $output .= sprintf( "nop" );
         break;
      case OP_JZ_I64:
         $output .= sprintf( "jz lbl%d",
            $this->args[ 0 ]->value );
         break;
      case OP_SETZ_R8:
         $output .= sprintf( "setz %s",
            self::getRegName( $this->args[ 0 ]->value ) );
         break;
      case OP_SETNZ_R8:
         $output .= sprintf( "setnz %s",
            self::getRegName( $this->args[ 0 ]->value ) );
         break;
      case OP_SETL_R8:
         $output .= sprintf( "setl %s",
            self::getRegName( $this->args[ 0 ]->value ) );
         break;
         /*
      case OP_LBL:
         $output .= sprintf( "lbl%d:",
            $this->args[ 0 ]->value );
         break;
         */
      case OP_MOV_R64I64:
         $output .= sprintf( "mov %s, %d",
            self::getRegName( $this->args[ 0 ]->value ),
            $this->args[ 1 ]->value );
         break;
      case OP_CMP_R64I64:
         $output .= sprintf( "cmp %s, %d",
            self::getRegName( $this->args[ 0 ]->value ),
            $this->args[ 1 ]->value );
         break;
      case OP_CMP_R64R64:
         $output .= sprintf( "cmp %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_TEST:
         $output .= sprintf( "test %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_MOV_R64R64:
         $output .= sprintf( "mov %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_MOV_R64M64:
         $output .= sprintf( "mov %s, [rsp+%d]",
            self::getRegName( $this->args[ 0 ]->value ),
            $this->args[ 1 ]->value );
         break;
      case OP_ADD_R64R64:
         $output .= sprintf( "add %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_MOV_M64R64:
         $output .= sprintf( "mov [rsp+%d], %s",
            $this->args[ 0 ]->value,
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_ADD:
         $output .= sprintf( "add %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_SUB_RR:
         $output .= sprintf( "sub %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      case OP_SUB_RI:
         $output .= sprintf( "sub %s, %d",
            self::getRegName( $this->args[ 0 ]->value ),
            $this->args[ 1 ]->value );
         break;
      case OP_CALL:
         $output .= sprintf( "call %s",
            $this->args[ 0 ]->value );
         break;
      case OP_JMP:
         $output .= sprintf( "jmp %s",
            $this->args[ 0 ]->value );
         break;
      case OP_JZ:
         $output .= sprintf( "jz %s",
            $this->args[ 0 ]->value );
         break;
      case OP_LABEL:
         $output .= sprintf( "%s:",
            $this->args[ 0 ]->value );
         break;
      case OP_RET:
         $output .= sprintf( "ret" );
         break;
      case OP_MOV:
         $output .= sprintf( "mov " );
         #$output .= sprintf( "mov %s, %d",
         #   self::getRegName( $this->destination ),
         #   $this->value );
         break;
      case OP_MOVZX_R64R8:
         $output .= sprintf( "movzx %s, %s",
            self::getRegName( $this->args[ 0 ]->value ),
            self::getRegName( $this->args[ 1 ]->value ) );
         break;
      default:
         UNREACHABLE();
      }
      if ( $debug ) {
         if ( $this->value != null ) {
            $output .= sprintf( " value=%d", $this->value->id );
            if ( count( $this->value->deps ) > 0 ) {
               $output .= sprintf( " deps=" );
               $output .= $this->dumpDeps( $this->value );
            }
         }
      }

      if ( $this->comment != '' ) {
         $output .= sprintf( ' ; %s', $this->comment );
      }
      return $output;

      printf( "%s", self::getInstructionName( $this->opcode ) );
      if ( ! empty( $this->args ) ) {
         printf( " " );
         foreach ( $this->args as $i => $arg ) {
            switch ( $arg->type ) {
            case ARG_IMM:
               printf( "%d", $arg->value );
               break;
            case ARG_REG:
               printf( "%s", self::getRegName( $arg->value ) );
               break;
            case ARG_MEM:
               printf( "%s", $arg->value );
               break;
            }
            if ( $i + 1 < count( $this->args ) ) {
               printf( ", " );
            }
         }
      }
      printf( "\n" );
   }

   private function dumpDeps( Value $value ): string {
      $output = '';
      foreach ( $value->deps as $i => $dep ) {
         $output .= sprintf( "value%d", $dep->id );
         if ( $i + 1 < count( $value->deps ) ) {
            $output .= printf( "," );
         }
      }
      return $output;
   }

   public function appendImm( int $imm ): void {
      $arg = new InstructionArg( ARG_IMM, $imm );
      array_push( $this->args, $arg );
   }

   public function appendReg( int $reg ): void {
      $arg = new InstructionArg( ARG_REG, $reg );
      array_push( $this->args, $arg );
   }

   public function appendMem( int $addr ): void {
      $arg = new InstructionArg( ARG_MEM, $addr );
      array_push( $this->args, $arg );
   }

   public static function getInstructionName( int $opcode ): string {
      $names = [
         OP_NOP => 'nop',
         OP_RET => 'ret',
         OP_MOV_R64I64 => 'mov_r64i64',
         OP_MOV_R64R64 => 'mov_r64r64',
         OP_MOV_R64M64 => 'mov_r64m64',
         OP_ADD_R64R64 => 'add_r64r64',
      ];
      if ( array_key_exists( $opcode, $names ) ) {
         return $names[ $opcode ];
      }
      else {
         throw new \Exception();
      }
   }

   public static function getRegName( int $reg ): string {
      $names = [
         REG_RAX => 'rax',
         REG_RBX => 'rbx',
         REG_RCX => 'rcx',
         REG_RDX => 'rdx',
         REG_RSI => 'rsi',
         REG_RDI => 'rdi',
         REG_RBP => 'rbp',
         REG_RSP => 'rsp',
         REG_R8 => 'r8',
         REG_R9 => 'r9',
         REG_R10 => 'r10',
         REG_R11 => 'r11',
         REG_R12 => 'r12',
         REG_R13 => 'r13',
         REG_R14 => 'r14',
         REG_R15 => 'r15',
         REG_AL => 'al',
         REG_BL => 'bl',
         REG_CL => 'cl',
         REG_AH => 'ah',
         REG_BH => 'bh',
         REG_CH => 'ch',
      ];
      if ( array_key_exists( $reg, $names ) ) {
         return $names[ $reg ];
      }
      else {
         throw new \Exception();
      }
   }
}
