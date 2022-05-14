<?php

declare( strict_types = 1 );

namespace Parse;

use Module;
use Task;
use TypeParam;
use TypeRequest;
use User;
use Typing;
use Lexing;

class DeclParser extends Parser {
   private Module $module;

   public function __construct( private Task $task, User $user,
      Lexing\Lexer $lexer,
      Lexing\ScopeLexer $scopeLexer, Module $module ) {
      parent::__construct( $user, $lexer, $scopeLexer );
      $this->module = $module;
   }

   public function readDecl( array $attrs, bool $visible ): \Node {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_CONST:
         return $this->readConstant( $visible );
      case TK_ENUM:
      case TK_VARIAN:
         return $this->readEnum( $visible );
      case TK_STRUCT:
      case TK_TRAIT:
         return $this->readStruct( $attrs, $visible );
      case TK_FUN:
      case TK_VIRT:
         return $this->parseFunc( $attrs, $visible );
      case TK_GEN:
         return $this->readGeneric( $attrs, $visible );
      default:
         throw new \Exception();
      }
   }

   private function readConstant( bool $visible ): \Constant {
      $this->testKw( TK_CONST );
      $constant = new \Constant();
      $constant->pos = $this->scopeLexer->token->pos;
      $constant->visible = $visible;
      $this->readTk();
      $name = $this->readName();
      $constant->pos = $name->pos;
      $constant->name = $name->value;
      $constant->name2 = $name;
      if ( $this->scopeLexer->tk === TK_COLON ) {
         $this->readTk();
         $constant->typeExpr = $this->readTypeExpr();
      }
      $this->testTk( TK_EQ );
      $this->readTk();
      $constant->value = $this->readExpr();
      $this->testTk( TK_SEMICOLON );
      $this->readTk();
      return $constant;
   }

   public function readEnum( bool $visible ): \Enumeration {
      if ( $this->scopeLexer->tkPotentialKw() !== TK_VARIAN ) {
         $this->testKw( TK_ENUM );
      }
      $this->readTk();
      $enum = new \Enumeration();
      $enum->pos = $this->scopeLexer->token->pos;
      $enum->visible = $visible;
      $enum->tagged = true;
      $this->parseEnumName( $enum );
      $this->parseEnumBody( $enum );
      return $enum;
   }

   private function parseEnumName( \Enumeration $enum ): void {
      if ( $this->scopeLexer->tk == TK_ID ) {
         $enum->name = $this->scopeLexer->copyTokenText();
         $this->readTk();
         if ( $this->scopeLexer->tk == TK_LBRAC ) {
            $this->readTk();
            if ( $this->scopeLexer->tk != TK_RBRAC ) {
               $enum->params = $this->readTypeParameterList();
            }
            $this->testTk( TK_RBRAC );
            $this->readTk();
         }
      }
   }

   private function parseEnumBody( \Enumeration $enum ): void {
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $this->readEnumeratorList( $enum );
      $this->testTk( TK_RPAREN );
      $this->readTk();
   }

   private function readEnumeratorList( \Enumeration $enum ): void {
      while ( true ) {
         $this->readEnumerator( $enum );
         if ( $this->scopeLexer->tk == TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk != TK_ID ) {
               break;
            }
         }
         else {
            break;
         }
      }
   }

   private function readEnumerator( \Enumeration $enum ): void {
      $this->testTk( TK_ID );
      $enumerator = new \Enumerator();
      $enumerator->pos = $this->scopeLexer->token->pos;
      $enumerator->name = $this->scopeLexer->copyTokenText();
      $enumerator->enumeration = $enum;
      $enumerator->visible = $enum->visible;
      $enum->body[] = $enumerator;
      $this->readTk();

      /*
      if ( $this->scopeLexer->tk === TK_EQ ) {
         $this->readTk();
         $enumerator->initializer = $this->readExpr();
      }
      */

      if ( $this->scopeLexer->tk === TK_COLON ) {
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_EQ ) {
            $enumerator->expectedType = $this->readTypeExpr();
         }
         if ( $this->scopeLexer->tk === TK_EQ ) {
            $this->readTk();
            $enumerator->initializer = $this->readExpr();
         }
      }
      else if ( $this->scopeLexer->tk === TK_LPAREN ) {
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_RPAREN ) {
            $enumerator->params = $this->readParamList();
         }
         $this->testTk( TK_RPAREN );
         $this->readTk();
      }
   }

   private function readExpr(): \Expr {
      $exprParser = new ExprParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      $expr = $exprParser->readExpr();
      $this->tk = $this->scopeLexer->tk;
      return $expr;
   }

   private function readTypeExpr(): \TypeExpr {
      $exprParser = new TypeExprParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      $expr = $exprParser->readTypeExpr();
      $this->tk = $this->scopeLexer->tk;
      return $expr;
   }

   public function readAnonStruct( array $attrs, bool $visible ): \Structure {
      $this->testTk( TK_LBRAC );
      $structure = new \Structure();
      $structure->pos = $this->scopeLexer->token->pos;
      $structure->attrs = $attrs;
      $structure->visible = $visible;
      $structure->defined = true;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RBRAC ) {
         $structure->members = $this->readStructMemberList();
      }
      $this->testTk( TK_RBRAC );
      $this->readTk();
      return $structure;
   }

   public function readStruct( array $attrs, bool $visible ): \Structure {
      $trait = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_TRAIT ) {
         $trait = true;
      }
      else {
         $this->testKw( TK_STRUCT );
      }
      $structure = new \Structure();
      $structure->pos = $this->scopeLexer->token->pos;
      $structure->attrs = $attrs;
      $structure->visible = $visible;
      $structure->trait = $trait;
      $this->readTk();
      if ( $this->scopeLexer->tk === TK_ID ) {
         $structure->name = $this->scopeLexer->copyTokenText();
         $structure->pos = $this->scopeLexer->token->pos;
         $this->readTk();
      }
      $this->readStructParameters( $structure );
      // if ( $this->tk == TK_IMPLEMENTS ) {
      //   $impl = $this->readImplementation();
      //}
      //else {
      if ( $this->scopeLexer->tk == TK_LPAREN ) {
         $this->testTk( TK_LPAREN );
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_RPAREN ) {
            $structure->members = $this->readStructMemberList();
         }
         $structure->defined = true;
         $this->testTk( TK_RPAREN );
         $this->readTk();
         if ( $this->scopeLexer->tkPotentialKw() == TK_IMPLEMENTS ) {
            $structure->impls = $this->readImplementationList();
         }
      }
      else if ( $this->scopeLexer->tkPotentialKw() == TK_IMPLEMENTS ) {
         $structure->impls = $this->readImplementationList();
      }
      else {
         $this->testTk( TK_SEMICOLON );
         $this->readTk();
      }
      //}
      return $structure;
   }

   private function readStructParameters( \Structure $structure ): void {
      if ( $this->scopeLexer->tk === TK_LBRAC ) {
         $structure->params = $this->readTypeParameters();
         if ( count( $structure->params ) > 0 ) {
            $structure->generic = true;
         }
      }
   }

   /**
    * @return TypeParam[]
    */
   private function readTypeParameters(): array {
      $this->testTk( TK_LBRAC );
      $this->readTk();
      $params = $this->readTypeParameterList();
      $this->testTk( TK_RBRAC );
      $this->readTk();
      return $params;
   }

   /**
    * @return TypeParam[]
    */
   private function readTypeParameterList(): array {
      $params = [];
      $done = false;
      while ( ! $done ) {
         $param = $this->readTypeParameter();
         $params[] = $param;
         switch ( $this->tk ) {
         case TK_COMMA:
            $this->readTk();
            break;
         default:
            $done = true;
         }
      }
      return $params;
   }

   private function readTypeParameter(): TypeParam {
      $this->testTk( TK_ID );
      $param = new TypeParam();
      $param->pos = $this->scopeLexer->token->pos;
      $param->name = $this->scopeLexer->copyTokenText();
      $this->readTk();
      if ( $this->tk == TK_COLON ) {
         $this->readTk();
         $param->expectedType = $this->readTypeExpr();
      }
      return $param;
   }

   /**
    * @return \StructureMember[]
    */
   private function readStructMemberList(): array {
      $members = [];

      while ( true ) {
         $members[] = $this->readStructMember();
         if ( $this->scopeLexer->tk === TK_COMMA ) {
            $this->readTk();
            if ( ! $this->isStartOfStructMember() ) {
               break;
            }
         }
         else {
            break;
         }
      }

      return $members;
   }

   private function isStartOfStructMember(): bool {
   /*
      while ( $this->lexer->tk == TK_NL ) {
         $this->readTk();
      }
   */
      switch ( $this->scopeLexer->tk ) {
      case TK_ID:
      case TK_UNDERSCORE:
      case TK_PUB:
         return true;
      default:
         return false;
      }
   }

   private function readStructMember(): \StructureMember {
      $visible = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_PUB ) {
         $visible = true;
         $this->readTk();
      }

      $virtual = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_VIRT ) {
         $virtual = true;
         $this->readTk();
      }

      $mutable = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_REB ) {
         $mutable = true;
         $this->readTk();
      }

      $member = new \StructureMember();
      $member->pos = $this->scopeLexer->token->pos;
      $member->visible = $visible;
      $member->virtual = $virtual;
      $member->mutable = $mutable;

      $name = '';
      if ( $this->scopeLexer->peek() === TK_COLON ) {
         if ( $this->scopeLexer->tk !== TK_UNDERSCORE ) {
            $this->testTk( TK_ID );
            $name = $this->scopeLexer->copyTokenText();
         }
         $this->readTk();
         $this->testTk( TK_COLON );
         $this->readTk();
      }

      $member->name = $name;

      $member->typeExpr = $this->readTypeExpr();
      if ( $this->scopeLexer->tk === TK_EQ ) {
         $this->readTk();
         $member->defaultInitializer = $this->readExpr();
      }

      return $member;
   }

   public function readTypeRequest(): TypeRequest {
      $request = new TypeRequest();

      if ( $this->scopeLexer->tk === TK_QUESTION_MARK ) {
         $option = new \TypeOption();
         $option->pos = $this->scopeLexer->token->pos;
         $option->refinements = $this->readRefinements();
         $request->options[] = $option;
      }
      else {
         while ( true ) {
            $option = $this->readRefinedTypeOption();
            $request->options[] = $option;
            if ( $this->scopeLexer->tk === TK_BAR ) {
               $this->readTk();
            }
            else {
               break;
            }
         }
      }

      return $request;
   }

   private function readRefinedTypeOption(): \TypeOption {
      $option = $this->readImportantTypeOption();
      if ( $this->scopeLexer->tk === TK_QUESTION_MARK ) {
         $option->refinements = $this->readRefinements();
      }
      return $option;
   }

   private function readRefinements(): array {
      $this->testTk( TK_QUESTION_MARK );
      $this->readTk();
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $refinements = $this->readRefinementList();
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $refinements;
   }

   private function readRefinementList(): array {
      $refinements = [];
      while ( $this->scopeLexer->tk === TK_ID ) {
         $refinement = new \Refinement();
         $refinement->pos = $this->scopeLexer->token->pos;
         $refinement->target = $this->scopeLexer->copyTokenText();
         $this->readTk();
         $this->testTk( TK_COLON );
         $this->readTk();
         $refinement->refinedTypeRequest = $this->readTypeRequest();
         $refinements[] = $refinement;
         if ( $this->scopeLexer->tk === TK_COMMA ) {
            $this->readTk();
         }
         else {
            break;
         }
      }
      return $refinements;
   }

   private function readImportantTypeOption(): \TypeOption {
      $option = $this->readBorrowedTypeOption();
      if ( $this->scopeLexer->tk === TK_BANG ) {
         $option->important = true;
         $this->readTk();
      }
      return $option;
   }

   private function readBorrowedTypeOption(): \TypeOption {
      $borrowed = false;
      if ( $this->scopeLexer->tk === TK_BITAND ) {
         $borrowed = true;
         $this->readTk();
      }
      $option = $this->readTypeOption();
      $option->borrowed = $borrowed;
      return $option;
   }

   private function readTypeOption(): \TypeOption {
      $option = new \TypeOption();
      $option->pos = $this->scopeLexer->token->pos;

      if ( $this->scopeLexer->tkPotentialKw() === TK_MUT ) {
         $option->mutable = true;
         $this->readTk();
      }

      if ( $this->scopeLexer->tkPotentialKw() === TK_ID || $option->mutable ) {
         return $this->readTypeOptionStartedById( $option );
      }
      else if ( $this->scopeLexer->tk === TK_LPAREN ) {
         return $this->readTypeTuple( $option );
      }
      else if ( $this->scopeLexer->tkPotentialKw() === TK_STRUCT ) {
         $structure = $this->readStruct( [], false );
         $option->syntax = TYPE_OPTION_STRUCT;
         $option->structure = $structure;
      }
      else if ( $this->scopeLexer->tk === TK_LBRAC ) {
         $structure = $this->readAnonStruct( [], false );
         $option->syntax = TYPE_OPTION_STRUCT;
         $option->structure = $structure;
      }
      /*
      else if ( $this->scopeLexer->tk === TK_INTEGER_LITERAL ) {
         $request->name = 'Int';
         $tuple = new \Tuple();
         $tuple->args
         $request->expectedMemberValues =
         $this->readTk();
      }*/
      else if ( $this->tk == TK_FUN ) {
         $option->func = $this->readFuncType();
      }
      else if (
         $this->scopeLexer->tk === TK_MINUS ||
         $this->scopeLexer->tk === TK_PLUS ||
         $this->scopeLexer->tk === TK_INTEGER_LITERAL ) {
         $minus = false;
         if ( $this->scopeLexer->tk === TK_MINUS ) {
            $this->readTk();
            $this->testTk( TK_INTEGER_LITERAL );
            $minus = true;
         }
         else if ( $this->scopeLexer->tk === TK_PLUS ) {
            $this->readTk();
            $this->testTk( TK_INTEGER_LITERAL );
         }
         $option->syntax = TYPE_OPTION_INTEGER;
         /*
         $option->name = 'Int';
         $refinement = new \Refinement();
         $refinement->pos = $option->pos;
         $refinement->type = REFINEMENT_INTEGER;
         $refinement->target = 'value';
         $option->refinements[] = $refinement;
         */
         $option->value = ( int ) $this->scopeLexer->copyTokenText();
         if ( $minus ) {
            $option->value = - $option->value;
         }
         $this->readTk();
         return $option;
      }
      else if (
         $this->scopeLexer->tkPotentialKw() === TK_TRUE ||
         $this->scopeLexer->tkPotentialKw() === TK_FALSE ) {
         $option->syntax = TYPE_OPTION_BOOL;
         $option->value = $this->scopeLexer->tkPotentialKw() == TK_TRUE ? 1 : 0;
         $this->readTk();
         return $option;
      }
      else if ( $this->scopeLexer->tk === TK_STRING_LITERAL ) {
         $option->syntax = TYPE_OPTION_STR;
         $option->value = $this->task->internString(
            $this->scopeLexer->copyTokenText() );
         $this->readTk();
         return $option;
      }

      /*
      // TEMPORARY
      else if ( $this->tk == TK_PTR ) {
         $this->readTk();
         $this->testTk( TK_LBRAC );
         $this->readTk();
         $ptr = new \Ptr();
         if ( $this->scopeLexer->tk == TK_RBRAC ) {
            $ptr->type = $this->voidType;
         }
         else {
            $ptr->type = $this->readTypeRequest();
         }
         $type->ptr = $ptr;
         $this->testTk( TK_RBRAC );
         $this->readTk();
      }
      */

      else {
         $this->diag( DIAG_SYNTAX | DIAG_ERR, $this->scopeLexer->token->pos,
            "expecting type parameter" );
         $this->bail();
      }
      return $option;
   }

   private function readTypeOptionStartedById(
      \TypeOption $option ): \TypeOption {
      $this->testTk( TK_ID );
      $option->pos = $this->scopeLexer->token->pos;
      $option->syntax = TYPE_OPTION_NAME;
      $option->name = $this->scopeLexer->copyTokenText();
      $this->readTk();
      if ( $this->scopeLexer->tk === TK_LBRAC ) {
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_RBRAC ) {
            $option->args = $this->readTypeArgumentList();
         }
         $this->testTk( TK_RBRAC );
         $this->readTk();
      }
      else if ( $this->scopeLexer->tk === TK_LPAREN ) {
         $this->readTk();
         $option->refinements = $this->readRefinementList();
         $this->testTk( TK_RPAREN );
         $this->readTk();
      }
      return $option;
   }

   private function readTypeTuple( \TypeOption $option ): \TypeOption {
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $option->syntax = TYPE_OPTION_TUPLE;
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $option->args = $this->readTypeArgumentList();
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $option;
   }

   private function readFuncType(): \FuncType {
      $this->testTk( TK_FUN );
      $this->readTk();
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $type = new \FuncType();
      if ( $this->tk != TK_RPAREN ) {
         $type->params = $this->readFuncTypeParamList();
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      $type->returnType = $this->readReturnType();
      return $type;
   }

   private function readFuncTypeParamList(): array {
      $params = [];
      while ( true ) {
         $params[] = $this->readTypeRequest();
         if ( $this->tk == TK_COMMA ) {
            $this->readTk();
         }
         else {
            break;
         }
      }
      return $params;
   }

   /**
    * @return TypeRequest[]
    */
   public function readTypeArgumentList(): array {
      $args = [];
      while ( true ) {
         $name = '';
         if ( ( $this->scopeLexer->tk === TK_ID &&
            $this->scopeLexer->peek() === TK_COLON ) ) {
            $name = $this->scopeLexer->copyTokenText();
            $this->readTk();
            $this->testTk( TK_COLON );
            $this->readTk();
         }

         $request = $this->readTypeRequest();
         $request->name = $name;
         $args[] = $request;

         if ( $this->tk == TK_COMMA ) {
            $this->readTk();
         }
         else {
            break;
         }
      }
      return $args;
   }

   private function readImplementationList(): array {
      $impls = [];
      while ( true ) {
         $impl = $this->readImplementation();
         $impls[] = $impl;
         if ( $this->scopeLexer->tkPotentialKw() !== TK_IMPLEMENTS ) {
            break;
         }
      }
      return $impls;
   }

   private function readImplementation(): \Implementation {
      $this->testKw( TK_IMPLEMENTS );
      $impl = new \Implementation();
      $impl->pos = $this->lexer->token->pos;
      $this->readTk();
      if ( $this->scopeLexer->tk == TK_ID ) {
         $this->testTk( TK_ID );
         $impl->traitName = $this->scopeLexer->copyTokenText();
         $this->readTk();
         $this->readImplementationParameterList( $impl );
         /*
         $this->testTk( TK_COLONCOLON );
         $this->readTk();
         if ( $this->tk == TK_ID ) {
            $impl->traitFuncName = $this->lexer->copyTokenText();
            $this->readTk();
         }*/
      }
      $this->testTk( TK_LBRACE );
      $this->readTk();
      $this->readImplementationItemList( $impl );
      $this->testTk( TK_RBRACE );
      $this->readTk();
      return $impl;
   }

   private function readImplementationParameterList(
      \Implementation $impl ): void {
      if ( $this->scopeLexer->tk == TK_LBRAC ) {
         $this->readTk();
         if ( $this->scopeLexer->tk != TK_RBRAC ) {
            $impl->params = $this->readTypeParameterList();
            if ( count( $impl->params ) > 0 ) {
               $impl->generic = true;
            }
         }
         $this->testTk( TK_RBRAC );
         $this->readTk();
      }
   }

   private function readImplementationItemList( \Implementation $impl ): void {
      while ( $this->isImplementationItem() ) {
         $func = $this->readImplementationItem( $impl );
         array_push( $impl->funcs, $func );
      }
   }

   private function isImplementationItem(): bool {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_PUB:
      case TK_FUN:
         return true;
      default:
         return false;
      }
   }

   private function readImplementationItem(): \Func {
      $visible = $this->readVisibility();
      $func = $this->readFunc();
      $func->visible = $visible;
      return $func;
   }

   private function readVisibility(): bool {
      $visible = false;
      if ( $this->scopeLexer->tkPotentialKw() == TK_PUB ) {
         $visible = true;
         $this->readTk();
      }
      return $visible;
   }

   private function readTrait(): \TraitObj {
      $this->testKw( TK_TRAIT );
      $this->readTk();
      $this->testTk( TK_ID );
      $trait = new \TraitObj();
      $trait->name = $this->scopeLexer->copyTokenText();
      $this->readTk();
      $this->readTraitParameterList( $trait );
      $this->testTk( TK_LBRACE );
      $this->readTk();
      $trait->members = $this->readTraitMemberList();
      $this->testTk( TK_RBRACE );
      $this->readTk();
      return $trait;
   }

   private function readTraitParameterList( \TraitObj $trait ): void {
      if ( $this->scopeLexer->tk == TK_LBRAC ) {
         $this->readTk();
         if ( $this->scopeLexer->tk != TK_RBRAC ) {
            $trait->params = $this->readTypeParameterList();
            if ( count( $trait->params ) > 0 ) {
               $trait->generic = true;
            }
         }
         $this->testTk( TK_RBRAC );
         $this->readTk();
      }
   }

   private function readTraitMemberList(): array {
      $members = [];
      while ( $this->tk != TK_RBRACE ) {
         $member = new \TraitMember();
         $member->func = $this->readFuncHeader();
         $this->testTk( TK_SEMICOLON );
         $this->readTk();
         array_push( $members, $member );
      }
      return $members;
   }

   private function parseFunc( array $attrs, bool $visible ): \Func {
      $func = $this->readFunc();
      $func->attrs = $attrs;
      $func->visible = $visible;
      $this->module->funcs[] = $func;
      return $func;
   }

   public function readFunc(): \Func {
      $func = $this->readFuncHeader();
      if ( $this->scopeLexer->tk == TK_SEMICOLON ) {
         $this->readTk();
      }
      else {
         $func->body = $this->readFuncBody();
      }
      #$this->scope->pop();
      return $func;
   }

   private function readFuncHeader(): \Func {
      $virtual = false;
      if ( $this->scopeLexer->tkPotentialKw() == TK_VIRT ) {
         $virtual = true;
         $this->readTk();
      }
      $this->testKw( TK_FUN );
      $func = new \Func();
      $func->pos = $this->scopeLexer->token->pos;
      $func->virtual = $virtual;
      $this->readTk();
      if ( $this->scopeLexer->tk === TK_ID ) {
         $func->pos = $this->scopeLexer->token->pos;
         $func->name = $this->scopeLexer->copyTokenText();
         $this->scopeLexer->bindName( $func->name );
         $this->readTk();
      }
      if ( $this->scopeLexer->tk === TK_LBRAC ) {
         $func->typeParams = $this->readTypeParameters();
      }
      $this->testTk( TK_LPAREN );
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $this->readFuncParamList( $func );
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      [ $params, $returnType ] = $this->readReturnType();
      $func->returnParams = $params;
      $func->returnTypeExpr = $returnType;
      return $func;
   }

   private function readFuncParamList( \Func $func ): void {
      if ( $this->scopeLexer->tk === TK_STAR ) {
         $func->argsParam = $this->readArgsParam( $func );
      }
      else {
         $func->params = $this->readParamList();
         if ( $this->scopeLexer->tk === TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk !== TK_RPAREN ) {
               $func->argsParam = $this->readArgsParam( $func );
            }
         }
      }
   }

   /**
    * @return \Param[]
    */
   private function readParamList(): array {
      // Leading comma is optional.
      if ( $this->scopeLexer->tk === TK_COMMA ) {
         $this->readTk();
      }
      $done = false;
      $params = [];
      while ( ! $done ) {
         $param = $this->readParam();
         $params[] = $param;
         if ( $this->scopeLexer->tk === TK_COMMA ) {
            if ( $this->scopeLexer->peek() === TK_ID ) {
               $this->readTk();
            }
            else {
               $done = true;
            }
         }
         else {
            $done = true;
         }
      }
      return $params;
   }

   private function readParam(): \Param {
      if ( $this->scopeLexer->tkPotentialKw() === TK_LET ) {
         $this->readTk();
         $this->testTk( TK_LPAREN );
         $this->readTk();
         $this->readUnpackedTuple();
         $this->testTk( TK_RPAREN );
         $this->readTk();
         $param = new \Param();
         $param->pos = $this->scopeLexer->token->pos;
         $param->name = '';
         return $param;
      }
      else {
         $rebindable = false;
         if ( $this->scopeLexer->tkPotentialKw() === TK_REB ) {
            $rebindable = true;
            $this->readTk();
         }

         $param = new \Param();
         $param->pos = $this->scopeLexer->token->pos;
         $param->rebindable = $rebindable;

         if ( $this->scopeLexer->tk === TK_ID &&
            $this->scopeLexer->peek() === TK_COLON ) {
            $param->name = $this->scopeLexer->copyTokenText();
            $this->readTk();
            $this->testTk( TK_COLON );
            $this->readTk();
         }

         if ( $this->scopeLexer->tk !== TK_EQ ) {
            //$param->expectedType = $this->readTypeRequest();
            $typeExprParser = new TypeExprParser( $this->task, $this->user,
               $this->lexer, $this->scopeLexer, $this->module );
            $param->expectedTypeExpr = $typeExprParser->readTypeExpr();
            //$param->expectedTypeExpr = $this->readConstantExpr();
         }

         if ( $this->scopeLexer->tk === TK_EQ ) {
            $this->readTk();
            $param->defaultArg = $this->readExpr();
         }

         return $param;
      }

      #$this->scope->bind( $param->name, $param );

   }

   private function readArgsParam(): \Param {
      $this->testTK( TK_STAR );
      $this->readTk();
      $param = new \Param();
      $param->pos = $this->scopeLexer->token->pos;
      $param->name = '';
      if ( $this->scopeLexer->tk === TK_ID ) {
         $param->name = $this->scopeLexer->copyTokenText();
         $this->readTk();
         if ( $this->tk === TK_COLON ) {
            $this->readTk();
            $param->expectedType = $this->readTypeExpr();
         }
      }
      return $param;
   }

   private function readReturnType(): array {
      $params = [];
      $type = null;
      if ( $this->scopeLexer->tk == TK_ARROW ) {
         $this->readTk();
         /*
         if ( $this->scopeLexer->tk == TK_LPAREN ) {
            $this->readTk();
            if ( $this->scopeLexer->tk != TK_RPAREN ) {
               $params = $this->readParamList();
            }
            $this->testTk( TK_RPAREN );
            $this->readTk();
         }
         else {
         */
         $type = $this->readTypeExpr();
         /*
         // Hoist any declared types into the outer scope.
         // TODO: Make this work for local functions.
         foreach ( $request->options as $option ) {
            if ( $option->structure !== null ) {
               $this->module->items[] = $option->structure;
            }
         }
         */
         //}
      }
      else if ( $this->scopeLexer->tk === TK_QUESTION_MARK ) {
         $type = $this->readTypeExpr();
      }

      return [ $params, $type ];
   }

   private function readFuncBody(): \BlockStmt {
      $stmtParser = new ExprParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      return $stmtParser->readBlockStmt();
   }

   public function readUnpackedTuple(): array {
      $params = [];
      //$this->testTk( TK_LPAREN );
      //$this->readTk();
      //if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         if ( $this->scopeLexer->tk === TK_STAR ) {
            $params[] = $this->readArgsParam();
         }
         else {
            $params = $this->readParamList();
            if ( $this->scopeLexer->tk === TK_COMMA ) {
               $this->readTk();
               $params[] = $this->readArgsParam();
            }
         }
      //}
      //$this->testTk( TK_RPAREN );
      //$this->readTk();
      return $params;
   }

   private function readGeneric( array $attrs, bool $visible ): \Generic {
      $this->testKw( TK_GEN );
      $generic = new \Generic();
      $generic->pos = $this->scopeLexer->token->pos;
      $generic->attrs = $attrs;
      $generic->visible = $visible;
      $this->readTk();

      if ( $this->scopeLexer->tk === TK_ID ) {
         $generic->pos = $this->scopeLexer->token->pos;
         $generic->name = $this->scopeLexer->copyTokenText();
         $this->scopeLexer->bindName( $generic->name );
         $this->readTk();
      }

      $this->testTk( TK_LBRAC );
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RBRAC ) {
         // $generic->params = $this->readParamList();
         $generic->params = $this->readTypeParameterList();
      }
      $this->testTk( TK_RBRAC );
      $this->readTk();

      $generic->body = $this->readFuncBody();

      return $generic;
   }
}
