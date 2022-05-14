<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

class Writer {
   private int $varIdx = 0;

   public function write( CTranslationUnit $unit, string $outputPath ): void {
      $content = new CContent();
      $this->writeTranslationUnit( $content, $unit );
      file_put_contents( $outputPath, $content->output );
   }

   private function writeTranslationUnit( CContent $content,
      CTranslationUnit $unit ): void {
      $content->append( "#include <stdio.h>\n" );
      $content->append( "#include <stdlib.h>\n" );
      $content->append( "#include <stdbool.h>\n" );
      $content->append( "#include <stdint.h>\n" );

      // Error reporting.
      $content->append( "\n" );
      $content->append( "void* err( const char* message ) {\n" );
      $content->append( "   printf( \"error: %%s\\n\", message );\n" );
      $content->append( "   exit( 1 );\n" );
      $content->append( "}\n" );

      $this->writeMacros( $content, $unit );
      $this->writeDeclarations( $content, $unit );
      $this->writePrototypes( $content, $unit );
      $this->writeStructs( $content, $unit );
      $this->writeVars( $content, $unit );
      foreach ( $unit->funcs as $func ) {
         $this->writeFunc( $content, $func );
      }
   }

   private function writeMacros( CContent $content,
      CTranslationUnit $unit ): void {
      $content->newline();
      $content->append( "// Macros\n" );
      $content->append( "#define ALLOC( var ) \\\n" );
      $content->append( "   var = malloc( sizeof( *var ) ); \\\n" );
      $content->append( "   if ( var == NULL ) { \\\n" );
      $content->append( "      err( \"memory allocation failure\" ); \\\n" );
      $content->append( "   }\n" );
      $content->newline();
      $content->append( "#define DROP( var ) \\\n" );
      $content->append( "   if ( --var->rc == 0 ) { \\\n" );
      $content->append( "      free( var ); \\\n" );
      $content->append( "   }\n" );
   }

   private function writeDeclarations( CContent $content,
      CTranslationUnit $unit ): void {
      $structs = $unit->structs->getAll();
      if ( count( $structs ) > 0 ) {
         $content->newline();
         $content->comment( 'Struct declarations' );
         foreach ( $structs as $struct ) {
            $this->writeStructDeclaration( $content, $struct );
         }
      }
   }

   private function writeStructDeclaration( CContent $content,
      CStruct $struct ): void {
      $this->writeStructHeader( $content, $struct );
      $content->append( ";\n" );
   }

   private function writeStructHeader( CContent $content,
      CStruct $struct ): void {
      if ( $struct->union ) {
         $content->append( 'union' );
      }
      else {
         $content->append( 'struct' );
      }
      if ( $struct->index !== -1 ) {
         $content->append( ' s%d', $struct->index );
      }
   }

   private function writePrototypes( CContent $content,
      CTranslationUnit $unit ): void {
      if ( count( $unit->funcs ) > 0 ) {
         $content->newline();
         $content->comment( "Prototypes" );
         foreach ( $unit->funcs as $func ) {
            $this->writeFuncPrototype( $content, $func );
         }
      }
   }

   public function writeFuncPrototype( CContent $content, CFunc $func ): void {
      $this->writeFuncHeader( $content, $func );
      $content->write( ";\n" );
   }

   private function writeFuncHeader( CContent $content, CFunc $func ): void {
      if ( $func->static ) {
         $content->append( 'static ' );
      }
      $this->writeCType( $content, $func->returnType );
      $content->write( ' %s', $func->name );
      $this->writeParamList( $content, $func );
   }

   private function writeParamList( CContent $content, CFunc $func ): void {
      $content->write( '( ' );
      if ( count( $func->params ) > 0 ) {
         $added = false;
         foreach ( $func->params as $param ) {
            if ( $added ) {
               $content->write( ', ' );
            }
            $this->writeParam( $content, $param );
            $added = true;
         }

         if ( $func->variadic ) {
            $content->append( ', ...' );
         }
      }
      else {
         $this->writeType( $content, SPEC_VOID );
      }
      $content->write( ' )' );
   }

   private function writeParam( CContent $content, CParam $param ): void {
      $this->writeCType( $content, $param->type );
      if ( $param->name !== '' ) {
         $content->write( ' %s', $param->name );
      }
   }

   private function writeCType( CContent $content, CType $type ): void {
      $this->writeType( $content, $type->spec, $type->struct, $type->pointers,
         false, $type->const );
   }

   private function writeType( CContent $content, int $spec,
      ?CStruct $struct = null, array $pointers = [], bool $static = false,
      bool $const = false, array $params = [] ) {
      if ( $static ) {
         $content->append( 'static ' );
      }
      if ( $const ) {
         $content->append( 'const ' );
      }
      switch ( $spec ) {
      case SPEC_STRUCTPTR:
         $this->writeStructHeader( $content, $struct );
         $content->append( '*' );
         break;
      case SPEC_STRUCT:
         $this->writeStructHeader( $content, $struct );
         break;
      case SPEC_NESTED_STRUCT:
         $this->writeStructBlock( $content, $struct );
         break;
      case SPEC_BOOL:
         $content->append( 'bool' );
         break;
      case SPEC_VOID:
         $content->append( 'void' );
         break;
      case SPEC_INT8:
         $content->append( 'int8_t' );
         break;
      case SPEC_INT16:
         $content->append( 'int16_t' );
         break;
      case SPEC_INT32:
         $content->append( 'int32_t' );
         break;
      case SPEC_INT64:
         $content->append( 'int64_t' );
         break;
      case SPEC_UINT8:
         $content->append( 'uint8_t' );
         break;
      case SPEC_UINT16:
         $content->append( 'uint16_t' );
         break;
      case SPEC_UINT32:
         $content->append( 'uint32_t' );
         break;
      case SPEC_UINT64:
         $content->append( 'uint64_t' );
         break;
      case SPEC_CHAR:
         $content->append( 'char' );
         break;
      case SPEC_STR:
         $content->append( 'const char*' );
         break;
      default:
         $content->append( 'int' );
         break;
      }
      $this->writePointer( $content, $pointers );
   }

   private function writePointer( CContent $content, array $pointers ): void {
      foreach ( $pointers as $pointer ) {
         $content->append( '*' );
         if ( $pointer->constant ) {
            $content->append( ' const' );
         }
      }
   }

   private function writeStructs( CContent $content,
      CTranslationUnit $unit ): void {
      $content->newline();
      $content->comment( 'Structs' );
      $structs = $unit->structs->getAll();
      $count = 0;
      foreach ( $structs as $struct ) {
         $this->writeStruct( $content, $struct );
         if ( $count + 1 < count( $structs ) ) {
            $content->newline();
         }
         ++$count;
      }
   }

   private function writeStruct( CContent $content, CStruct $struct ): void {
      $this->writeStructBlock( $content, $struct );
      $content->append( ";\n" );
   }

   private function writeStructBlock( CContent $content,
      CStruct $struct ): void {
      if ( $struct->originalName !== '' ) {
         $content->comment( '`%s`', $struct->originalName );
      }
      $this->writeStructHeader( $content, $struct );
      $content->append( " {\n" );
      $content->indent();

      if ( $struct->refCounted ) {
         $content->append( "uint64_t rc;\n" );
      }

      foreach ( $struct->members as $member ) {
         $this->writeStructMember( $content, $member );
      }
      $content->dedent();
      $content->append( "}" );
   }

   private function writeStructMember( CContent $content,
      CStructMember $member ): void {
      $this->writeCType( $content, $member->type );
      if ( $member->traitMember ) {
         $content->append( '* ( *%s )( void* )', $member->name );
      }
      else if ( $member->traitMethod !== null ) {
         $content->append( '* ( *%s )', $member->name );
         $this->writeParamList( $content, $member->traitMethod );
      }
      else if ( $member->declarator != null ) {
         $this->writeDeclarator( $content, $member->declarator );
      }
      else {
         if ( $member->name !== '' ) {
            $content->append( " %s", $member->name );
         }
         else {
            $content->append( " m%d", $member->index );
         }
         foreach ( $member->dims as $dim ) {
            $content->append( '[ %d ]', $dim );
         }
      }
      $content->append( ";\n" );
   }

   private function writeDeclarator( CContent $content,
      CDeclarator $declarator ): void {
      $this->writePointer( $content, $declarator->pointers );
      if ( $declarator->parens != null ) {
         $content->append( ' ( ' );
         $this->writeDeclarator( $content, $declarator->parens );
         $content->append( ' )' );
      }
      else {
         $content->append( "%s", $declarator->name );
      }
      if ( $declarator->params != null ) {
         $content->append( '( ' );
         if ( count( $declarator->params->params ) > 0 ) {
            foreach ( $declarator->params->params as $i => $param ) {
               $this->writeParam( $content, $param );
               if ( $i + 1 < count( $declarator->params->params ) ) {
                  $content->append( ', ' );
               }
            }
         }
         else {
            $content->append( 'void' );
         }
         $content->append( ' )' );
      }
      else {
         foreach ( $declarator->dims as $dim ) {
            $content->append( '[ %d ]', $dim );
         }
      }
   }

   private function writeVars( CContent $content,
      CTranslationUnit $unit ): void {
      if ( count( $unit->vars ) > 0 || count( $unit->strings ) > 0 ) {
         $content->newline();
         $content->comment( 'Variables' );
         foreach ( $unit->vars as $var ) {
            $this->writeVar( $content, $var );
         }

         // String table.
         if ( count( $unit->strings ) > 0 ) {
            $content->append( "const char* %s[] = {\n", STR_TABLE_VAR );
            $content->indent();
            foreach ( $unit->strings as $string ) {
               $content->append( "\"%s\",\n", $string );
            }
            $content->dedent();
            $content->append( "};\n" );
         }
      }
   }

   private function writeVar( CContent $content,
      CVar $var ): void {
      $this->writeType( $content, $var->type->spec, $var->type->struct,
         $var->type->pointers, $var->static, $var->const );
      $content->append( ' %s', $var->name );
      foreach ( $var->dims as $dim ) {
         $content->append( '[ %d ]', $dim );
      }
      if ( $var->initializer != null ) {
         $content->append( " = {\n" );
         $content->indent();
         foreach ( $var->initializer->children as $child ) {
            $this->writeExpr( $content, $child );
            $content->append( ",\n" );
         }
         $content->dedent();
         $content->append( "}" );
      }
      $content->append( ";\n" );
   }

   private function writeFunc( CContent $content, CFunc $func ): void {
      if ( $func->body !== null ) {
         $content->newline();
         $this->writeFuncHeader( $content, $func );
         $content->append( ' ' );
         $this->writeFuncBody( $content, $func );
      }
   }

   private function writeFuncBody( CContent $content, CFunc $func ): void {
      $content->append( "{\n" );
      $content->indent();

      // Self parameter.
      if ( $func->selfParam !== null ) {
         $this->writeVarUsage( $content, $func->selfParam->var );
         $content->append( '%s', $func->selfParam->name );
         $content->append( ";\n" );
      }

      // Write local variables.
      foreach ( $func->body->vars as $var ) {
         $this->writeVar( $content, $var );
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

      $this->writeStmtList( $content, $func->body->items );

      if ( $func->body->cleanup !== null ) {
         $content->append( "cleanup:\n" );
         /*
               $this->writeStmtList( $content, $func->body->cleanup );
         */
      }

      if ( $func->body->returnValue !== null ) {
         $content->append( 'return ' );
         $this->writeVarUsage( $content, $func->body->returnValue );
         $content->append( ";\n" );
      }
      else {
         $content->append( "return;\n" );
      }

      $content->dedent();
      $content->append( "}\n" );
   }

   private function writeCompoundStmt( CContent $content,
      Group $stmt ): void {
      $content->append( "{\n" );
      $content->indent();
      $this->writeStmtList( $content, $stmt );
      $content->dedent();
      $content->append( "}\n" );

      /*
      if ( ! $stmt->groupOnly ) {
         $content->append( "{\n" );
         $content->indent();
      }
      foreach ( $stmt->allocs as $alloc ) {
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
      $this->writeStmtList( $content, $stmt );
      if ( ! $stmt->groupOnly ) {
         $content->dedent();
         $content->append( "}\n" );
      }*/
   }

   private function createVarIndex(): int {
      return $this->varIdx++;
   }

   public function writeStmtList( CContent $content, Group $group ): void {
      foreach ( $group->operations as $operation ) {
         $this->writeStmt( $content, $operation );
      }
   }

   private function writeStmt( CContent $content,
      CNode $stmt ): void {
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
         /*
      case NODE_ASSERT:
         $this->writeAssert( $content, $stmt );
         break;
         */
      case CNODE_INTEGER_LITERAL_ASSIGNMENT:
         $this->writeIntegerLiteralAssignment( $content, $stmt );
         break;
      case CNODE_STRING_LITERAL_ASSIGNMENT:
         $this->writeStringLiteralAssignment( $content, $stmt );
         break;
      case CNODE_BINARY:
         $this->writeBinary( $content, $stmt );
         break;
      case CNODE_ASSIGNMENT:
         $this->writeAssignment( $content, $stmt );
         break;
      case CNODE_CALL:
         $this->writeCall( $content, $stmt );
         break;
      case CNODE_POINTER_DEREF:
         $this->writePointerDeref( $content, $stmt );
         break;
      case CNODE_UNARY:
         $this->writeUnary( $content, $stmt );
         break;
      case CNODE_ALLOC:
         $this->writeAlloc( $content, $stmt );
         break;
      case CNODE_EXPR:
         $this->writeExprStmt( $content, $stmt );
         break;
      case CNODE_ERR:
         $this->writeErr( $content, $stmt );
         break;
      case CNODE_DEREF:
         $this->writeDeref( $content, $stmt );
         break;
      case CNODE_SUBSCRIPT:
         $this->writeSubscript( $content, $stmt );
         break;
      case CNODE_SHARE:
         $this->writeShare( $content, $stmt );
         break;
      case CNODE_UNION_VALUE:
         $this->writeUnionValue( $content, $stmt );
         break;
      case CNODE_TAG_MATCH:
         $this->writeTagMatch( $content, $stmt );
         break;
      case CNODE_UNION_ACCESS:
         $this->writeUnionAccess( $content, $stmt );
         break;
      default:
      var_dump( get_class( $stmt ) );
         throw new \Exception();
      }
   }

   private function writeCleanupStmt( CContent $content,
      CCleanupStmt $stmt ): void {
      if ( $stmt->alloc != null ) {
         $stmt->object = $stmt->alloc->name;
      }
      $content->append( 'if ( ! --%s->rc ) ', $stmt->object );
      if ( $stmt->struct->cleanupFunc != null ) {
         $content->append( '%s', $stmt->struct->cleanupFunc->name );
      }
      else {
         $content->append( 'free' );
      }
      $content->append( "( %s );\n", $stmt->object );
   }

   private function writeEnumCleanupStmt( CContent $content,
      CEnumCleanupStmt $stmt ): void {
      $content->append( "switch ( %s->tag ) {\n", $stmt->param->name );
      foreach ( $stmt->cases as $case ) {
         $content->append( "case %d:\n", $case->tag );
         $content->indent();
         foreach ( $case->params as $param ) {
            $this->writeCleanupStmt( $content, $param );
         }
         $content->append( "break;\n" );
         $content->dedent();
      }
      $content->append( "}\n" );
      $content->append( "free( %s );\n", $stmt->param->name );
   }

   private function writeFreeStmt( CContent $content, CFreeStmt $stmt ): void {
      $content->append( "DROP( %s );\n", $stmt->var->name );
   }

   private function writeErrStmt( CContent $content, CErrStmt $stmt ): void {
      $content->append( 'err( "%s" )', $stmt->message );
      $content->append( ";\n" );
   }

   private function writeIfStmt( CContent $content, CIfStmt $stmt ): void {
      $content->append( 'if ( ' );
      $this->writeVarUsage( $content, $stmt->cond );
      $content->append( ' != 0 ) ' );
      $this->writeCompoundStmt( $content, $stmt->body );
      if ( $stmt->elseBody != null ) {
         $content->append( 'else ' );
         $this->writeCompoundStmt( $content, $stmt->elseBody );
      }
   }

   private function writeSwitchStmt( CContent $content,
      CSwitchStmt $stmt ): void {
      $this->writeTopExpr( $content, $stmt->cond, true );
      $content->append( 'switch ( %s ) ', $stmt->cond->outputVar );
      $this->writeCompoundStmt( $content, $stmt->body );
   }

   private function writeCase( CContent $content, CCase $case ): void {
      $content->dedent();
      $content->append( "case %d:\n", $case->value );
      $content->indent();
   }

   private function writeDefaultCase( CContent $content ): void {
      $content->dedent();
      $content->append( "default:\n" );
      $content->indent();
   }

   private function writeWhileStmt( CContent $content,
      CWhileStmt $stmt ): void {
      $content->append( "while ( 1 ) {\n" );
      $content->indent();
      $this->writeStmtList( $content, $stmt->condGroup );
      $content->append( 'if ( ! ' );
      $this->writeVarUsage( $content, $stmt->cond );
      $content->append( " ) { break; }\n" );
      //$this->writeTopExpr( $content, $stmt->cond, true );
      //$content->append( 'while ( %s ) ', $stmt->cond->outputVar );
      $this->writeStmtList( $content, $stmt->body );
      $content->dedent();
      $content->append( "}\n" );
   }

   private function writeBreak( CContent $content ): void {
      $content->append( "break;\n" );
   }

   private function writeReturnStmt( CContent $content,
      CReturnStmt $stmt ): void {
      $content->append( "return" );
      if ( $stmt->value !== null ) {
         $content->append( " " );
         $this->writeVarUsage( $content, $stmt->value );
      }
      $content->append( ";\n" );
      /*
      if ( $stmt->value !== null ) {
         //$this->writeVarUsage( $content, $stmt->value, true );
         $content->append( "retVal = " );
         $this->writeVarUsage( $content, $stmt->value );
         $content->append( ";\n" );
         $content->append( "goto cleanup;\n" );
      }
      else {
         $content->append( "return;\n" );
      }
      */
   }

   private function writeAssert( CContent $content, CAssert $assert ): void {
      $this->writeTopExpr( $content, $assert->expr, true );
      $content->append( 'if ( ! ( %s', $assert->expr->outputVar );
      $content->append( ' ) ) {' );
      $content->indent();
      $content->append( "\n" );
      $content->append( 'printf( "%%s:%%d:%%d: assertion failure\n", ' .
         '"%s", %d, %d );', $assert->file, $assert->line, $assert->column );
      $content->append( "\n" );
      $content->append( "exit( 1 );\n" );
      $content->dedent();
      $content->append( "}\n" );
   }

   private function writeExprStmt( CContent $content,
      CExprStmt $stmt ): void {
      $this->writeTopExpr( $content, $stmt->expr, false );
      $content->append( ";\n" );
   }

   private function writeTopExpr( CContent $content, CExpr $expr,
      bool $allocOutputVar ): void {
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
      }
      if ( $allocOutputVar ) {
         $expr->outputVar = sprintf( "b%d", $this->createVarIndex() );
         $this->writeType( $content, $expr->type, $expr->struct );
         $content->append( ' %s = ', $expr->outputVar );
      }
      */
      $this->writeExpr( $content, $expr );
      if ( $allocOutputVar ) {
         $content->append( ";\n" );
      }
   }

   private function writeExpr( CContent $content, CExpr $expr ): void {
      $this->writeRoot( $content, $expr->root );
   }

   private function writeRoot( CContent $content, CNode $node ): void {
      switch ( $node->nodeType ) {
      case CNODE_RETURN_STMT:
         $this->writeReturnStmt( $content, $node );
         break;
      case CNODE_ASSIGNMENT:
         $this->writeAssignment( $content, $node );
         break;
      case CNODE_REPLACE_REF:
         $this->writeReplaceRef( $content, $node );
         break;
      case CNODE_BINARY:
         $this->writeBinary( $content, $node );
         break;
      case CNODE_SUBSCRIPT:
         $this->writeSubscript( $content, $node );
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
      }
   }

   private function writeAssignment( CContent $content,
      CAssignment $assignment ): void {
      if ( $assignment->deref ) {
         $content->append( '*' );
      }
      $this->writeVarUsage( $content, $assignment->lside );
      $content->append( ' = ' );
      $this->writeVarUsage( $content, $assignment->rside );
      $content->append( ";\n" );
   }

   private function writeReplaceRef( CContent $content,
      CReplaceRef $replaceRef ): void {
      $this->writeRoot( $content, $replaceRef->member );
      $content->append( " = " );
      $this->writeRoot( $content, $replaceRef->replacement );
      $content->append( ";\n" );

      $content->append( "++" );
      $this->writeRoot( $content, $replaceRef->replacement );
      $content->append( "->rc;\n" );
   }

   private function writeBinary( CContent $content, CBinary $binary ): void {
      $this->writeVarUsage( $content, $binary->result );
      $content->append( ' = ' );
      $this->writeVarUsage( $content, $binary->lside );
      switch ( $binary->op ) {
      case CBINARY_EQ: $content->append( ' == ' ); break;
      case CBINARY_NEQ: $content->append( ' != ' ); break;
      case CBINARY_LT: $content->append( ' < ' ); break;
      case CBINARY_LTE: $content->append( ' <= ' ); break;
      case CBINARY_GT: $content->append( ' > ' ); break;
      case CBINARY_GTE: $content->append( ' >= ' ); break;
      case CBINARY_ADD: $content->append( ' + ' ); break;
      case CBINARY_SUB: $content->append( ' - ' ); break;
      case CBINARY_MUL: $content->append( ' * ' ); break;
      case CBINARY_DIV: $content->append( ' / ' ); break;
      case CBINARY_MOD: $content->append( ' %% ' ); break;
      case CBINARY_LOGAND: $content->append( ' && ' ); break;
      case CBINARY_LOGOR: $content->append( ' || ' ); break;
      }
      $this->writeVarUsage( $content, $binary->rside );
      $content->append( ";\n" );
   }

   private function writeSubscript( CContent $content,
      CSubscript $subscript ): void {
      $content->append( '%s = ', $subscript->result->name );
      $this->writeVarUsage( $content, $subscript->base );
      $content->append( '->m0[ ' );
      $this->writeVarUsage( $content, $subscript->index );
      $content->append( " ];\n" );
   }

   private function writeAccess( CContent $content, CAccess $access ): void {
      $this->writeRoot( $content, $access->object );
      $content->append( '.%s', $access->member );
   }

   private function writeCall( CContent $content, CCall $call ): void {
      if ( $call->returnValue !== null ) {
         $this->writeVarUsage( $content, $call->returnValue );
         $content->append( ' = ' );
      }
      switch ( $call->func ) {
      case CCALL_PRINTF:
         $content->append( 'printf' );
         break;
      case CCALL_USER:
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
            if ( $arg->addrof ) {
               $content->append( '&' );
            }
            $this->writeVarUsage( $content, $arg->var );
            $argsAdded = true;
         }
         $content->append( ' ' );
      }
      $content->append( ')' );
      $content->append( ";\n" );
   }

   private function writeDeref( CContent $content, CDeref $deref ): void {
      //$this->writeRoot( $content, $deref->operand );
      $content->append( '%s = ', $deref->result->name );
      $this->writeVarUsage( $content, $deref->operand );
      if ( $deref->subscript ) {
         $content->append( '->m0[ %d ]', $deref->member );
      }
      else {
         $content->append( '->m%d', $deref->member );
      }
      $content->append( ";\n" );
      //if ( $deref->isBool ) {
      //   $content->append( '->value' );
      //}
   }

   private function writeShare( CContent $content, CShare $share ): void {
      $content->append( "++%s->rc;\n", $share->var->name );
   }

   private function writeUnionValue( CContent $content,
      CUnionValue $wrapper ): void {
      $content->append( "ALLOC( %s );\n", $wrapper->result->name );
      $content->append( "%s->rc = 1;\n", $wrapper->result->name );
      $content->append( "%s->tag = %d;\n", $wrapper->result->name,
         $wrapper->member );
      $member = sprintf( 'm%d', $wrapper->member );
      $content->append( "%s->u.%s = ", $wrapper->result->name, $member );
      $this->writeVarUsage( $content, $wrapper->value );
      $content->append( ";\n" );
   }

   private function writeTagMatch( CContent $content,
      CTagMatch $match ): void {
      $this->writeVarUsage( $content, $match->result );
      $content->append( " = " );
      $content->append( "%s->tag == %d", $match->operand->name,
         $match->member );
      /*
      $content->append( "ALLOC( %s );\n", $wrapper->result->name );
      $content->append( "%s->rc = 1;\n", $wrapper->result->name );
      $content->append( "%s->tag = %d;\n", $wrapper->result->name,
         $wrapper->member );
      $member = sprintf( 'm%d', $wrapper->member );
      $content->append( "%s->u.%s = ", $wrapper->result->name, $member );
      $this->writeVarUsage( $content, $wrapper->value );
      */
      $content->append( ";\n" );
   }

   private function writeUnionAccess( CContent $content,
      CUnionAccess $access ): void {
      $this->writeVarUsage( $content, $access->result );
      $content->append( " = " );
      $content->append( "%s->u.m%d", $access->operand->name,
         $access->member );
      /*
      $content->append( "ALLOC( %s );\n", $wrapper->result->name );
      $content->append( "%s->rc = 1;\n", $wrapper->result->name );
      $content->append( "%s->tag = %d;\n", $wrapper->result->name,
         $wrapper->member );
      $member = sprintf( 'm%d', $wrapper->member );
      $content->append( "%s->u.%s = ", $wrapper->result->name, $member );
      $this->writeVarUsage( $content, $wrapper->value );
      */
      $content->append( ";\n" );
   }

   private function writePrefix( CContent $content, CNode $node ): void {
      switch ( $node->nodeType ) {
      case CNODE_UNARY:
         $this->writeUnary( $content, $node );
         break;
      case CNODE_POINTER_DEREF:
         $this->writePointerDeref( $content, $node );
         break;
      default:
         $this->writePrimary( $content, $node );
      }
   }

   private function writeUnary( CContent $content, CUnary $unary ): void {
      if ( $unary->result !== null ) {
         $this->writeVarUsage( $content, $unary->result );
         $content->append( ' = ' );
      }
      switch ( $unary->op ) {
      case CUOP_MINUS:
         $content->write( '-%s', (
            $unary->operand->nodeType == CNODE_UNARY &&
            $unary->operand->op == CUOP_MINUS ) ? ' ' : '' );
         break;
      case CUOP_NOT:
         $content->write( '! ' );
         break;
      case CUOP_ADDROF:
         $content->write( '&' );
         break;
      case CUOP_PRE_INC:
         $content->write( '++' );
         break;
      case CUOP_PRE_DEC:
         $content->write( '--' );
         break;
      }
      $this->writeVarUsage( $content, $unary->operand );
      $content->append( ";\n" );
   }

   private function writePointerDeref( CContent $content,
      CPointerDeref $deref ): void {
      $this->writeVarUsage( $content, $deref->result );
      $content->append( ' = ' );
      if ( $deref->operand instanceof CParam ) {
         $content->append( '%s', $deref->operand->name );
      }
      else {
         $this->writeVarUsage( $content, $deref->operand );
      }
      if ( $deref->index !== null ) {
         $content->append( '[ ' );
         $this->writeVarUsage( $content, $deref->index );
         $content->append( ' ]' );
      }
      else {
         $content->append( '[ 0 ]' );
      }

      if ( $deref->value !== null ) {
         $content->append( ' = ' );
         $this->writeVarUsage( $content, $deref->value );
      }

      $content->append( ";\n" );
   }

   private function writePrimary( CContent $content, CNode $node ): void {
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
      case CNODE_INTEGER_LITERAL_ASSIGNMENT:
         $this->writeIntegerLiteralAssignment( $content, $node );
         break;
      case CNODE_STRING_LITERAL:
         $this->writeStringLiteral( $content, $node );
         break;
      case CNODE_PAREN:
         $this->writeParen( $content, $node );
         break;
      }
   }

   private function writeErr( CContent $content, CErr $err ): void {
      $content->append( 'err( "%s\n" )', $err->message );
   }

   private function writeNullPointer( CContent $content ): void {
      $content->append( 'NULL' );
   }

   private function writeCast( CContent $content, CCast $cast ): void {
      $content->append( '( ' );
      $content->append( '( ' );
      $this->writeType( $content, $cast->spec, $cast->struct,
         $cast->pointers );
      $content->append( ' )' );
      $content->append( '( ' );
      $this->writeExpr( $content, $cast->value );
      $content->append( ' )' );
      $content->append( ' )' );
   }

   private function writeNameUsage( CContent $content,
      CNameUsage $usage ): void {
      if ( $usage->isParam ) {
         if ( $usage->object->altName != '' ) {
            $content->append( '%s', $usage->object->altName );
         }
         else {
            $content->append( '%s', $usage->object->name );
         }
      }
      else {
         $content->append( '%s', $usage->name );
      }
   }

   private function writeVarUsage( CContent $content, CVar $var ): void {
      $content->append( '%s', $var->name );
   }

   private function writeAlloc( CContent $content, CAlloc $alloc ): void {
      if ( $alloc->skipMalloc ) {
         $content->append( "%s = ", $alloc->name );
         $this->writeExpr( $content, $alloc->initializer );
         $content->append( ";\n" );
      }
      else {
         $content->append( "ALLOC( %s );\n", $alloc->var->name );
      }
      //$content->append( '%s', $alloc->name );
      $content->append( "%s->rc = 1;\n", $alloc->var->name );
      foreach ( $alloc->initializers as $initializer ) {
         if ( $alloc->struct->homogeneous ) {
            $content->append( "%s->m0[ %d ] = ", $alloc->var->name,
               $initializer->memberInt );
         }
         else {
            $member = $initializer->member;
            if ( $member === '' ) {
               $member = sprintf( 'm%d', $initializer->memberInt );
            }
            $content->append( "%s->%s = ", $alloc->var->name, $member );
         }
         $this->writeVarUsage( $content, $initializer->value );
         $content->append( ";" );

         if ( $initializer->comment !== '' ) {
            $content->append( ' // init `%s`', $initializer->comment );
         }
         $content->append( "\n" );

         /*
         if ( $initializer->incRefCount ) {
            $content->append( "%s%s%s->rc++", $alloc->name,
               $alloc->stack ? '.' : '->', $initializer->member );
            $content->append( ";\n" );
         }
         */
      }
   }

   private function writeAllocInitializers( CContent $content,
      CAlloc $alloc ): void {
      foreach ( $alloc->initializers as $initializer ) {
         $content->append( "%s%s%s = ", $alloc->name,
            $alloc->stack ? '.' : '->', $initializer->member );
         $this->writeExpr( $content, $initializer->value );
         $content->append( ";\n" );
         if ( $initializer->incRefCount ) {
            $content->append( "%s%s%s->rc++", $alloc->name,
               $alloc->stack ? '.' : '->', $initializer->member );
            $content->append( ";\n" );
         }
      }
      /*
      foreach ( $alloc->struct->members as $k => $member ) {
         if ( $k >= count( $alloc->initializers ) ) {
            break;
         }
         $content->append( "%s->%s = ", $alloc->name, $member->name );
         $this->writeExpr( $content, $alloc->initializers[ $k ] );
         $content->append( ";\n" );
      } */
   }

   private function writeIntegerLiteral( CContent $content,
      CIntegerLiteral $literal ): void {
      $content->append( '%d', $literal->value );
   }

   private function writeIntegerLiteralAssignment( CContent $content,
      CIntegerLiteralAssignment $assignment ): void {
      $content->append( "%s = %d;\n", $assignment->var->name,
         $assignment->value );
   }

   private function writeStringLiteral( CContent $content,
      CStringLiteral $literal ): void {
      $content->append( '"%s"', $literal->value );
   }

   private function writeStringLiteralAssignment( CContent $content,
      CStringLiteralAssignment $assignment ): void {
      $content->append( "%s = \"%s\";\n", $assignment->var->name,
         $assignment->value );
   }

   private function writeParen( CContent $content,
      CParen $paren ): void {
      $content->append( '( ' );
      $this->writeExpr( $content, $paren->expr );
      $content->append( ' )' );
   }
}
