<?php

declare( strict_types = 1 );

namespace Codegen\Php;

class Writer {
   private int $varIdx = 0;

   public function write( PhpScript $script, string $outputPath ): void {
      $content = new Content();
      $this->writeScript( $content, $script );
      file_put_contents( $outputPath, $content->output );
      var_dump( $content->output );
   }

   private function writeScript( Content $content, PhpScript $script ): void {
      $content->append( "<?php\n\n" );
      $content->append( "declare( strict_types = 1 );\n\n" );
      foreach ( $script->funcs as $func ) {
         $this->writeFunc( $content, $func );
      }
      if ( array_key_exists( 'main', $script->funcsToPhpfuncs ) ) {
         $content->append( "main();\n" );
      }
   }

   private function writeFunc( Content $content, PhpFunc $func ): void {
      $content->write( 'function %s()', $func->name );
      if ( $func->body !== null ) {
         $this->writeFuncBody( $content, $func );
         #$this->writeFunc( $content, $func );
         #$content->append( ' ' );
         #$this->writeFuncBody( $content, $func );
      }
   }

   private function writeFunca( Content $content, PhpFunc $func ): void {
      return;
      if ( $func->returnType != null ) {
         $this->writeCType( $content, $func->returnType );
      }
      $content->write( ' %s', $func->name );
      $content->write( '( ' );
      // HACK.
      if ( $func->body != null ) {
         if ( count( $func->params ) > 0 ) {
            $added = false;
            foreach ( $func->params as $param ) {
               if ( $added ) {
                  $content->write( ', ' );
               }
               $this->writeParam( $content, $param );
               $added = true;
            }
         }
         else {
            $this->writeType( $content, SPEC_VOID );
         }
      }
      $content->write( ' )' );
   }

   private function writeFuncBody( Content $content, PhpFunc $func ): void {
      $content->append( " {\n" );
      $content->indent();
      /*
      if ( $func->spec != SPEC_VOID ) {
         $this->writeType( $content, $func->spec, $func->struct,
            $func->pointers );
         $content->append( " retVal;\n" );
      }
      foreach ( $func->body->allocs as $alloc ) {
         $alloc->name = sprintf( "b%d", $this->createVarIndex() );
         if ( $alloc->stack ) {
            $content->append( "struct %s %s;\n",
               $alloc->struct->name, $alloc->name );
         }
         else {
            $content->append( "struct %s* %s;\n",
               $alloc->struct->name, $alloc->name );
         }
      }
      */
      $this->writeStmtList( $content, $func->body );
      /*
      if ( $func->body->cleanup != null ) {
         $content->append( "cleanup:\n" );
         $this->writeStmtList( $content, $func->body->cleanup );
         if ( $func->spec != SPEC_VOID ) {
            $content->append( "return retVal;\n" );
         }
         else {
            $content->append( "return;\n" );
         }
      }*/
      $content->dedent();
      $content->append( "}\n" );
   }

   private function writeBlockStmt( Content $content,
      PhpBlockStmt $stmt ): void {
      $content->append( "{\n" );
      $content->indent();
      $this->writeStmtList( $content, $stmt );
      $content->dedent();
      $content->append( "}\n" );
   }

   public function writeStmtList( Content $content,
      PhpBlockStmt $stmt ): void {
      foreach ( $stmt->items as $childStmt ) {
         $this->writeStmt( $content, $childStmt );
      }
   }

   private function writeStmt( Content $content, PhpNode $stmt ): void {
      if ( $stmt instanceof PhpBlockStmt ) {
         $this->writeBlockStmt( $content, $stmt );
      }
      else if ( $stmt instanceof PhpIfStmt ) {
         $this->writeIfStmt( $content, $stmt );
      }
      else if ( $stmt instanceof PhpExprStmt ) {
         $this->writeExprStmt( $content, $stmt );
      }
      else if ( $stmt instanceof PhpStmtReturnValue ) {
         if ( $stmt->var !== null ) {
            $content->append( '$var = ' );
            $this->writeStmt( $content, $stmt->value );
            $content->append( ";\n" );
         }
         else {
            $content->append( 'return ' );
            $this->writeStmt( $content, $stmt->value );
            $content->append( ";\n" );
         }
      }
      else {
         \UNREACHABLE();
      }

      return;
      switch ( $stmt->nodeType ) {
      case CNODE_CLEANUP_STMT:
         $this->writeCleanupStmt( $content, $stmt );
         break;
      case CNODE_ENUM_CLEANUP_STMT:
         $this->writeEnumCleanupStmt( $content, $stmt );
         break;
      case CNODE_FREE_STMT:
         $this->writeFreeStmt( $content, $stmt );
         break;
      case CNODE_COMPOUND:
         $this->writeCompoundStmt( $content, $stmt );
         break;
      case CNODE_ERR_STMT:
         $this->writeErrStmt( $content, $stmt );
         break;
      case CNODE_IF:
         $this->writeIfStmt( $content, $stmt );
         break;
      case CNODE_SWITCH:
         $this->writeSwitchStmt( $content, $stmt );
         break;
      case CNODE_CASE:
         $this->writeCase( $content, $stmt );
         break;
      case CNODE_DEFAULT_CASE:
         $this->writeDefaultCase( $content );
         break;
      case CNODE_WHILE:
         $this->writeWhileStmt( $content, $stmt );
         break;
      case CNODE_BREAK:
         $this->writeBreak( $content );
         break;
      case CNODE_RETURN_STMT:
         $this->writeReturnStmt( $content, $stmt );
         break;
      case NODE_ASSERT:
         $this->writeAssert( $content, $stmt );
         break;
      case CNODE_EXPR:
         $this->writeExprStmt( $content, $stmt );
         break;
      }
   }

   private function writeIfStmt( Content $content, PhpIfStmt $stmt ): void {
      $this->writeTopExpr( $content, $stmt->cond, true );
      $content->append( 'if ( $var%d ) ', $stmt->cond->outputVar );
      $this->writeStmt( $content, $stmt->body );
      if ( $stmt->else !== null ) {
         $content->append( 'else ' );
         $this->writeStmt( $content, $stmt->else );
      }
   }

   private function writeExprStmt( Content $content,
      PhpExprStmt $stmt ): void {
      $this->writeTopExpr( $content, $stmt->expr, false );
      $content->append( ";\n" );
   }

   private function writeTopExpr( Content $content, PhpExpr $expr,
      bool $allocOutputVar = false ): void {
      /*
      foreach ( $expr->allocs as $alloc ) {
         if ( $alloc->skipMalloc ) {
            $content->append( "%s = ", $alloc->name );
            $this->writeExpr( $content, $alloc->initializer );
            $content->append( ";\n" );
         }
         else {
            $content->append( "%s = malloc( sizeof( *%s ) );\n",
               $alloc->name, $alloc->name );
         }
         $this->writeAllocInitializers( $content, $alloc );
      }*/
      if ( $allocOutputVar ) {
         $expr->outputVar = $this->varIdx;
         ++$this->varIdx;
         $content->append( "\$var%d = ", $expr->outputVar );
      }
      $this->writeExpr( $content, $expr );
      if ( $allocOutputVar ) {
         $content->append( ";\n" );
      }
   }

   private function writeExpr( Content $content, PhpExpr $expr ): void {
      $this->writeRoot( $content, $expr->root );
   }

   private function writeRoot( Content $content, PhpNode $node ): void {
      if ( $node instanceof PhpBlockStmt ) {
         $this->writeBlockStmt( $content, $node );
      }
      else if ( $node instanceof PhpIfStmt ) {
         $this->writeIfStmt( $content, $node );
      }
      else if ( $node instanceof PhpBinary ) {
         $this->writeBinary( $content, $node );
      }
      else if ( $node instanceof PhpCall ) {
         $this->writeCall( $content, $node );
      }
      else if ( $node instanceof PhpStmtWrapper ) {
         $content->append( "( function() {\n" );
         $content->indent();
         $this->writeStmt( $content, $node->stmt );
         $content->dedent();
         $content->append( "} )()" );
      }
      else {
         $this->writePrefix( $content, $node );
      }
      /*
      case CNODE_ASSIGNMENT:
         $this->writeAssignment( $content, $node );
         break;
      case CNODE_REPLACE_REF:
         $this->writeReplaceRef( $content, $node );
         break;
      case CNODE_BINARY:
         $this->writeBinary( $content, $node );
         break;
      case CNODE_ACCESS:
         $this->writeAccess( $content, $node );
         break;
      case CNODE_CALL:
         $this->writeCall( $content, $node );
         break;
      case CNODE_DEREF:
         $this->writeDeref( $content, $node );
         break;
      default:
         $this->writePrefix( $content, $node );
         break;
      }*/
   }

   private function writeBinary( Content $content, PhpBinary $binary ): void {
      $this->writeRoot( $content, $binary->lside );
      $this->writeRoot( $content, $binary->rside );
      switch ( $binary->op ) {
      case PHP_BINARY_EQ: $content->append( ' == ' ); break;
      case PHP_BINARY_NEQ: $content->append( ' != ' ); break;
      case PHP_BINARY_LT: $content->append( ' < ' ); break;
      case PHP_BINARY_LTE: $content->append( ' <= ' ); break;
      case PHP_BINARY_GT: $content->append( ' > ' ); break;
      case PHP_BINARY_GTE: $content->append( ' >= ' ); break;
      case PHP_BINARY_ADD: $content->append( ' + ' ); break;
      case PHP_BINARY_SUB: $content->append( ' - ' ); break;
      case PHP_BINARY_MUL: $content->append( ' * ' ); break;
      case PHP_BINARY_DIV: $content->append( ' / ' ); break;
      case PHP_BINARY_MOD: $content->append( ' %% ' ); break;
      case PHP_BINARY_LOGAND: $content->append( ' && ' ); break;
      case PHP_BINARY_LOGOR: $content->append( ' || ' ); break;
      }
      $this->allocVar( $content, $binary->result );
      $content->append( ";\n" );

     # $machine->add( $binary->
   }

   private function allocVar( Content $content, PhpVar $var ): void {
      $var->index = $this->varIdx;
      ++$this->varIdx;
      $content->append( "\$var%d = ", $var->index );
   }

   private function writePrefix( Content $content, PhpNode $node ): void {
      if ( $node instanceof PhpUnary ) {

      }
      else {
         $this->writePrimary( $content, $node );
      }
      /*
      case CNODE_UNARY:
         $this->writeUnary( $content, $node );
         break;
      case CNODE_POINTER_DEREF:
         $this->writePointerDeref( $content, $node );
         break;
      default:
         $this->writePrimary( $content, $node );
      }
      */
   }

   private function writeCall( Content $content, PhpCall $call ): void {
      switch ( $call->func ) {
      case PHP_CALL_PRINTF:
         $this->writePrintf( $content, $call );
         return;
         break;
      case PHP_CALL_USER:
         $content->append( '%s', $call->userFunc->name );
         break;
      case CCALL_OPERAND:
         $this->writeRoot( $content, $call->operand );
         break;
      }
      $content->append( '(' );
      if ( count( $call->args ) > 0 ) {
         $content->append( ' ' );
         $argsAdded = false;
         foreach ( $call->args as $arg ) {
            if ( $argsAdded ) {
               $content->append( ', ' );
            }
            $this->writeExpr( $content, $arg );
            $argsAdded = true;
         }
         $content->append( ' ' );
      }
      $content->append( ')' );
   }

   private function writePrintf( Content $content, PhpCall $call ): void {
      $content->append( 'printf( ' );
      $format = '';
      foreach ( $call->args as $arg ) {
         switch ( $arg->type ) {
         case PHP_TYPE_INT: $format .= '%d'; break;
         case PHP_TYPE_BOOL:
         case PHP_TYPE_STRING: $format .= '%s'; break;
         }
      }
      $content->append( "\"%s\\n\"", $format );
      foreach ( $call->args as $arg ) {
         $content->append( ', ' );
         $this->writeExpr( $content, $arg );
         if ( $arg->type == PHP_TYPE_BOOL ) {
            $content->append( ' ? "true" : "false"' );
         }
      }
      $content->append( ' )' );
   }

   private function writePrimary( Content $content, PhpNode $node ): void {
      if ( $node instanceof PhpVar ) {
         $this->writeVar( $content, $node );
      }
      if ( $node instanceof PhpVarUsage ) {
         $this->writeVarUsage( $content, $node );
      }
      else if ( $node instanceof PhpIntegerLiteral ) {
         $this->writeIntegerLiteral( $content, $node );
      }
      else if ( $node instanceof PhpBoolLiteral ) {
         $this->writeBoolLiteral( $content, $node );
      }
      else if ( $node instanceof PhpStringLiteral ) {
         $this->writeStringLiteral( $content, $node );
      }
      else if ( $node instanceof PhpParen ) {
         $this->writeParen( $content, $node );
      }
   /*
      switch ( $node->nodeType ) {
      case CNODE_ERR:
         $this->writeErr( $content, $node );
         break;
      case CNODE_VAR:
         $this->writeVar( $content, $node );
         $content->append( '%s', $node->name );
         break;
      case CNODE_NULL_POINTER:
         $this->writeNullPointer( $content );
         break;
      case CNODE_CAST:
         $this->writeCast( $content, $node );
         break;
      case CNODE_NAME_USAGE:
         $this->writeNameUsage( $content, $node );
         break;
      case CNODE_ALLOC:
         $this->writeAlloc( $content, $node );
         break;
      case CNODE_INTEGER_LITERAL:
         $this->writeIntegerLiteral( $content, $node );
         break;
      case CNODE_STRING_LITERAL:
         $this->writeStringLiteral( $content, $node );
         break;
      case CNODE_PAREN:
         $this->writeParen( $content, $node );
         break;
      }*/
   }

   private function writeVar( Content $content, PhpVar $var ): void {
      #$this->writeTopExpr( $content, $var->value, true );
      #$var->index = $var->value->outputVar;


      $this->writeTopExpr( $content, $var->value );

      /*
      $var->index = $this->varIdx;
      ++$this->varIdx;
      $content->append( "\$var%d", $var->index );
      if ( $var->value !== null ) {
         $content->append( ' = ' );
      } */
   }

   private function writeVarUsage( Content $content,
      PhpVarUsage $usage ): void {
      $content->append( "\$var%d", $usage->var->index );
   }

   private function writeIntegerLiteral( Content $content,
      PhpIntegerLiteral $literal ): void {
      $content->append( '%d', $literal->value );
   }

   private function writeBoolLiteral( Content $content,
      PhpBoolLiteral $literal ): void {
      $content->append( '%s', $literal->value ? 'true' : 'false' );
   }

   private function writeStringLiteral( Content $content,
      PhpStringLiteral $literal ): void {
      $content->append( '"%s"', $literal->value );
   }

   private function writeParen( Content $content, PhpParen $paren ): void {
      $content->append( '( ' );
      $this->writeExpr( $content, $paren->expr );
      $content->append( ' )' );
   }
}
