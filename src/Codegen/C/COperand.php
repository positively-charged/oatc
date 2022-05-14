<?php

declare( strict_types = 1 );

namespace Codegen\C;

const C_OP_VALUE = -1;
const C_OP_ADD = 0;
const C_OP_ASSIGNMENT = 1;
const C_OP_EQ = 2;

class COperand {
   public int $op;
   public int $type;
   public $value;

   public function add( COperand $lside, COperand $rside ): COperand {

   }

   public function assign( COperand $lside ): COperand {
      $result = new COperand;
      $result->lside = $lside;
      $result->rside = $this;
      $result->op = C_OP_ASSIGNMENT;
      return $result;
   }

   public function eq( COperand $rside ): COperand {
      $result = new COperand;
      $result->lside = $this;
      $result->rside = $rside;
      $result->op = C_OP_EQ;
      return $result;
   }
}
