<?php

declare( strict_types = 1 );

namespace Codegen\X64;

use Codegen\OatIr;

interface Set {
   function add( Instruction $instruction ): void;
   function get(): array;
}

/**
 * A linear sequence of x86-64 instructions.
 */
class Sequence {
   /** @var Instruction[] */
   public array $instructions;

   /** @var Block[] */
   public array $blocks;
   public Block $activeBlock;
   public Block $endBlock;
   private int $totalInstructions;

   public function __construct() {
      $this->instructions = [];
      $this->blocks = [];
      $this->totalInstructions = 0;
      $startBlock = $this->createBlock();
      $this->activeBlock = $startBlock;
      $this->endBlock = $this->addBlock();
      $this->activeBlock = $startBlock;
      array_push( $this->endBlock->parents, $this->activeBlock );
      /*
      $this->registers = [];
      $this->slotToRegister = [];
      for ( $id = 0; $id < REG_TOTAL; ++$id ) {
         $reg = new Register( $id );
         $this->registers[ $id ] = $reg;
      }*/
   }

   public function appendInstruction( Instruction $instruction ): void {
      if ( $instruction instanceof BinaryInstruction ) {
         $instruction->lside->lastSeen = $this->totalInstructions;
         $instruction->rside->lastSeen = $this->totalInstructions;
         switch ( $instruction->opcode ) {
         case OP_ADD:
            //array_push( $instruction->lside->dependencies,
            //   $instruction->rside );
            break;
         default:
            UNREACHABLE();
         }
      }
      // If a jump from the block was already previously provided, then
      // there's no point in executing any more instructions. Discard them.
      if ( $this->activeBlock->exitJump === null ) {
         $this->activeBlock->instructions[] = $instruction;
         ++$this->totalInstructions;
      }
   }

   public function createBlock(): Block {
      $block = new Block();
      $block->id = count( $this->blocks );
      array_push( $this->blocks, $block );
      return $block;
   }

   public function addBlock(): Block {
      $block = $this->createBlock();
      $block->next = $this->activeBlock->next;
      $this->activeBlock->next = $block;
      $this->activeBlock = $block;
      return $block;
   }

   public function finalize(): array {
      return $this->blocks;
   }

   private function create( int $opcode ): Instruction {
      $instruction = new Instruction( $opcode );
      return $instruction;
   }

   public function nop(): void {
      $this->appendInstruction( $this->create( OP_NOP ) );
   }

   public function add( int $opcode ): void {
      $this->append( $this->create( $opcode ) );
   }

   public function addI( int $opcode, int $value ): void {
      switch ( $opcode ) {
      case OP_JZ_I64:
      case OP_LBL:
         $instruction = $this->create( $opcode );
         $instruction->appendImm( $value );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function addM( int $opcode, string $addr ): void {
      switch ( $opcode ) {
      case OP_CALL:
      case OP_LABEL:
      case OP_JMP:
      case OP_JZ:
         $instruction = $this->create( $opcode );
         $instruction->appendMem( $addr );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function addRI( int $opcode, int $reg, int $imm ): void {
      switch ( $opcode ) {
      case OP_MOV_R64I64:
      case OP_CMP_R64I64:
      case OP_TEST:
         $instruction = $this->create( $opcode );
         $instruction->appendReg( $reg );
         $instruction->appendImm( $imm );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function addRM( int $opcode, int $reg, string $addr ): void {
      switch ( $opcode ) {
      case OP_MOV_R64M64:
         $instruction = $this->create( $opcode );
         $instruction->appendReg( $reg );
         $instruction->appendMem( $addr );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function addR( int $opcode, int $reg ): void {
      switch ( $opcode ) {
      case OP_SETZ_R8:
         $instruction = $this->create( $opcode );
         $instruction->appendReg( $reg );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function addRR( int $opcode, int $reg1, int $reg2 ): void {
      switch ( $opcode ) {
      case OP_ADD_R64R64:
      case OP_MOV_R64R64:
      case OP_CMP_R64R64:
      case OP_SUB_RR:
         $instruction = $this->create( $opcode );
         $instruction->appendReg( $reg1 );
         $instruction->appendReg( $reg2 );
         $this->append( $instruction );
         break;
      default:
         throw new Exception();
      }
   }

   public function movRR( int $dst, int $src ): void {
      $this->addRR( OP_MOV_R64R64, $dst, $src );
   }

   public function movI( int $value ): VirtualRegister {
      $instruction = $this->create( OP_MOV );
      $dst = $this->addRegArg( $instruction );
      $this->addImmArg( $instruction, $value );
      $this->append( $instruction );
      return $dst;
   }

   private function addImmArg( Instruction $instruction, int $imm ): int {
      $arg = new InstructionArg( ARG_IMM, $imm );
      array_push( $instruction->args, $arg );
      return $imm;
   }

   private function addRegArg( Instruction $instruction ): VirtualRegister {
      $reg = new VirtualRegister();
      $arg = new InstructionArg( ARG_REG, $reg );
      $arg->reg = $reg;
      array_push( $instruction->args, $arg );
      return $reg;
   }

   public function append( Instruction $instruction ): void {
   /*
      switch ( $instruction->opcode ) {
      case OP_MOV_R64I64:
         $reg = $this->registers[ $instruction->args[ 0 ]->value ];
         $reg->immediate = true;
         $reg->value = $instruction->args[ 1 ]->value;
         $reg->instruction = count( $this->instructions );
         $instruction->comment = "imm";
         break;
      case OP_MOV_R64R64:
         $rsideReg = $this->registers[ $instruction->args[ 1 ]->value ];
         if ( $rsideReg->immediate ) {
            $instruction->args[ 1 ]->type = ARG_IMM;
            $instruction->args[ 1 ]->value = $rsideReg->value;
            $instruction->opcode = OP_MOV_R64I64;
            unset( $this->instructions[ $rsideReg->instruction ] );
         }
         else {
            $instruction->comment = "a";
            $lsideReg = $this->registers[ $instruction->args[ 0 ]->value ];
            $lsideReg->alias = $rsideReg;
         }
         break;
      case OP_ADD_R64R64:
         $rsideReg = $this->registers[ $instruction->args[ 1 ]->value ];
         if ( $rsideReg->immediate ) {
            $instruction->args[ 1 ]->type = ARG_IMM;
            $instruction->args[ 1 ]->value = $rsideReg->value;
            $instruction->opcode = OP_ADD_R64I64;
            $instruction->comment = "add";
         }
         else {
            $lsideReg = $this->registers[ $instruction->args[ 0 ]->value ];
            if ( isset( $lsideReg->alias ) ) {
               $instruction->args[ 0 ]->value = $lsideReg->alias->id;
            }
         }
         break;
      case OP_SUB_RR:
         $rsideReg = $this->registers[ $instruction->args[ 1 ]->value ];
         if ( $rsideReg->immediate ) {
            $instruction->args[ 1 ]->type = ARG_IMM;
            $instruction->args[ 1 ]->value = $rsideReg->value;
            $instruction->opcode = OP_SUB_RI;
            $instruction->comment = "sub";
            unset( $this->instructions[ $rsideReg->instruction ] );
         }
         break;
      }*/
      array_push( $this->instructions, $instruction );
   }

   public function makeReturnable(): void {
      $lastInstruction = end( $this->instructions );
      if ( $lastInstruction !== null && $lastInstruction->opcode != OP_RET ) {
         $this->addRI( OP_MOV_R64I64, REG_RAX, 0 );
         $this->add( OP_RET );
      }
   }

   public function dump( bool $debug = false ): string {
      $output = '';
      foreach ( $this->instructions as $instruction ) {
         $output .= $instruction->dump( $debug );
         $output .= sprintf( "\n" );
      }
      return $output;
   }
}
