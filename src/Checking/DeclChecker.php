<?php

declare( strict_types = 1 );

namespace Checking;

use Enumeration;
use Lexing\Position;
use Scope;
use TraitObj;
use TypeParam;
use Typing\AnonymousUnion;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\PresenterUsage;
use Typing\Type;
use Typing\Description as Desc;

class DeclChecker {
   use DescriberUsage;
   use PresenterUsage;
   use InstanceCheckerUsage;

   private \User $user;
   private \Typing\TypeChecker $typeChecker;
   private ExprChecker $exprChecker;
   private \Scope $scope;
   private \Task $task;

   public function __construct( private ModuleChecker $moduleChecker, \Scope $scope,
      \Task $task,
      \Typing\TypeChecker $typeChecker, ExprChecker $exprChecker,
      private SimpleExprChecker $simpleExprChecker,
      private Describer $typeDescriber,
      private Presenter $typePresenter,
      private InstanceChecker $instanceChecker ) {
      $this->user = $task->user;
      $this->scope = $scope;
      $this->task = $task;
      $this->typeChecker = $typeChecker;
      $this->exprChecker = $exprChecker;
   }

   public function checkConstant( \Constant $constant ): void {
      $value = $this->exprChecker->checkExpr( $constant->value );
      $constant->value2 = $value;

      if ( ! $value->constant ) {
         $this->user->diag( DIAG_ERR, $constant->value->pos,
            'value not constant' );
         $this->user->bail();
      }

      if ( $constant->typeExpr !== null ) {
         $type = $this->exprChecker->checkTypeExpr( $constant->typeExpr );
         if ( ! $this->isInstanceOf( $value, $type ) ) {
            $type = new Type();
            $type->spec = TYPESPEC_ERR;
            $constant->diag = $this->user->diag( DIAG_ERR, $constant->pos,
               "constant value not of correct type" );
         }
         $constant->type = $type;
      }
      else {
         $constant->type = $value->type;
      }

      $constant->resolved = true;
   }

   private function createErr( Position $pos, string $message,
      ... $args ): Value {
      $result = new Value();
      $result->type->spec = TYPESPEC_ERR;
      $result->diag = $this->user->diag( DIAG_ERR, $pos, $message,
         ... $args );
      return $result;
   }

   public function checkEnum( Enumeration $enum ): void {
      //$this->scope->push();
      if ( ! $enum->resolved ) {
         $this->checkEnumName( $enum );
         $this->checkEnumBody( $enum );
         $enum->resolved = true;
      }
      //$this->scope->pop();
   }

   private function checkEnumName( Enumeration $enum ): void {
      foreach ( $enum->params as $param ) {
         $genericType = new \Structure();
         $genericType->name = $param->name;
         $genericType->placeholder = true;
         $this->scope->bind( $genericType->name, $genericType );
      }
   }

   private function checkEnumBody( Enumeration $enum ): void {
      foreach ( $enum->body as $enumerator ) {
         $this->checkEnumerator( $enum, $enumerator );
      }
   }

   private function checkEnumerator( Enumeration $enum,
      \Enumerator $enumerator ): void {
      #$this->scopeList->bind( $enumerator->name, $enumerator );

      // Parameters.
      $this->scope->enter();
      foreach ( $enumerator->params as $param ) {
         $param->type = $this->exprChecker->checkTypeExpr(
            $param->expectedType );
         if ( $this->isRefType( $param->type ) ) {
            $param->isRefType = true;
         }
      }
      $this->scope->leave();

      if ( $enumerator->expectedType !== null ) {
         $enumerator->type = $this->exprChecker->checkTypeExpr(
            $enumerator->expectedType );
      }

      if ( $enumerator->initializer !== null ) {
         $result = $this->exprChecker->checkExpr( $enumerator->initializer );
         $enumerator->value = $enumerator->initializer->value;
         $enumerator->result = $result;
         if ( $enumerator->type === null ) {
            $enumerator->type = $result->type;
         }
         else {
            if ( ! $this->isInstanceOf( $result, $enumerator->type ) ) {
               $this->user->diag( DIAG_ERR, $enumerator->initializer->pos,
                  'initializer type (`%s`) different from enumerator ' .
                  'type (`%s`) ', $this->presentType( $result->type ),
                  $this->presentType( $enumerator->type ) );
               $this->user->bail();
            }
         }
      }
   }

   public function checkStructPrototype( \Structure $structure ): void {
      $this->scope->enter();
      $this->checkStructAttrs( $structure );
      $this->checkStructParams( $structure );
      $this->scope->leave();
   }

   public function checkStruct( \Structure $structure ): void {
      if ( $structure->defined ) {
         $this->scope->enter();
         $this->checkStructAttrs( $structure );
         $this->checkStructParams( $structure );
         $this->checkStructMembers( $structure );
         $structure->resolved = true;
         $this->checkImplementationList( $structure, $structure->impls );
         $this->scope->leave();
      }
      else {
         $this->scope->enter();
         $this->checkStructAttrs( $structure );
         $this->checkStructParams( $structure );
         $structure->resolved = true;
         $this->scope->leave();
         if ( count( $structure->impls ) > 0 ) {
            $binding = $this->scope->get( $structure->name );
            if ( $binding !== null &&
               $binding->node instanceof \Structure &&
               ( $binding->node->defined ||
               $binding->node->builtin !== BUILTIN_STRUCTURE_NONE ) ) {
               $structure->resolved = true;
               $this->checkImplementationList( $binding->node,
                  $structure->impls );
               $binding->node->impls = array_merge( $binding->node->impls,
                  $structure->impls );
            }
            else {
               $this->user->diag( DIAG_ERR, $structure->pos,
                  'struct `%s` not found', $structure->name );
               $this->user->bail();
            }
         }
      }
   }

   public function checkStructLiteral( \Structure $structure ): void {
      if ( ! empty( $structure->name ) ) {
         $this->scope->bind( $structure->name, $structure );
      }
      $this->checkStruct( $structure );
      $this->moduleChecker->appendTupleStruct( $structure );
   }

   private function checkStructAttrs( \Structure $structure ): void {
      foreach ( $structure->attrs as $attr ) {
         switch ( $attr->name ) {
         case 'known':
            $this->checkKnownStruct( $structure, $attr );
            break;
         default:
            $this->user->diag( DIAG_ERR, $attr->pos,
               "invalid struct attribute: %s", $attr->name );
            $this->user->bail();
         }
      }
   }

   private function checkKnownStruct( \Structure $structure,
      \Attr $attr ): void {
      if ( count( $attr->args ) !== 1 ) {
         $this->user->diag( DIAG_ERR, $attr->pos,
            "`%s` attribute expects 1 argument", $attr->name );
         $this->user->bail();
      }

      $id = $this->checkArg( $attr->args[ 0 ] );
      if ( ! $this->isInstanceOf( $id,
         $this->typeChecker->createIntType() ) ) {
         $this->user->diag( DIAG_ERR, $attr->args[ 0 ]->expr->pos,
            "argument 1 (`%s`) of `%s` attribute must be of " .
            'Int type', $this->presentType( $id->type ), $attr->name );
         $this->user->bail();
      }

      if ( ! $id->constant ) {
         $this->user->diag( DIAG_ERR, $attr->args[ 0 ]->expr->pos,
            "argument 1 of `%s` attribute must be constant", $attr->name );
         $this->user->bail();
      }

      $structure->builtin = $id->inhabitant;
   }

   private function checkStructParams( \Structure $structure ): void {
      $this->checkTypeParams( $structure->params );
   }

   private function checkStructMembers( \Structure $structure ): void {
      foreach ( $structure->members as $member ) {
         if ( $member instanceof \StructureMember ) {
            $this->checkStructMember( $structure, $member );
         }
         $structure->size += $this->sizeOfType( $member->type );
      }

      $this->determineHomogeneous( $structure );
   }

   private function checkStructMember( \Structure $structure,
      \StructureMember $member ): void {
      if ( $member->typeExpr != null ) {
         $member->type = $this->exprChecker->checkTypeExpr( $member->typeExpr );
         if ( $member->type->structure === $structure &&
            ! $member->type->borrowed ) {
            $this->user->diag( DIAG_ERR, $member->pos,
               'recursive member type' );
            $this->user->bail();
         }

         if ( $member->defaultInitializer != null ) {
            $result = $this->exprChecker->checkExpr(
               $member->defaultInitializer );
            if ( ! $this->isInstanceOf( $result, $member->type ) ) {
               $this->user->diag( DIAG_ERR, $member->defaultInitializer->pos,
                  'default initializer type (`%s`) different from member ' .
                  'type `%s`', $this->presentType( $result->type ),
                  $this->presentType( $member->type ) );
               $this->user->bail();
            }
         }
      }
      else {
         if ( $member->defaultInitializer != null ) {
            $result = $this->exprChecker->checkExpr(
               $member->defaultInitializer );
            $member->type = $result->type;

            // TODO: Remove.
            $member->type->refinements = [];
         }
         else {
            $this->user->diag( DIAG_ERR, $member->pos,
               'member `%s` of struct `%s` is missing a type', $member->name,
               $this->typePresenter->presentStruct( $structure ) );
            $this->user->bail();
         }
      }
   }

   private function checkArg( \Arg $arg ): Value {
      return $this->exprChecker->checkExpr( $arg->expr );
   }

   private function checkImplementationList( \Structure $structure,
      array $impls ): void {
      $this->createSelfAlias( $structure );
      foreach ( $impls as $impl ) {
         $this->checkImpl( $structure, $impl );
      }
   }

   private function createSelfAlias( \Structure $structure ): void {
      $this->scope->bind( 'Self', $structure );
   }

   private function checkImpl( \Structure $structure,
      \Implementation $impl ): void {
      $trait = null;
      if ( $impl->traitName !== '' ) {
         $binding = $this->scope->get( $impl->traitName );
         if ( $binding !== null && $binding->node instanceof \Structure &&
            $binding->node->trait ) {
            $trait = $binding->node;
         }
         else {
            $this->user->diag( DIAG_ERR, $impl->pos,
               'trait `%s` not found', $impl->traitName );
            $this->user->bail();
         }
      }

      $scope = new Scope( $this->user, $this->task->module,
         $this->typeDescriber );

      foreach ( $impl->funcs as $func ) {
         $this->checkFunc( $func );
         $impl->funcTable[ $func->name ] = $func;
         $scope->bind( $func->name, $func );
         switch ( $func->name ) {
         case '__eq': $structure->operators->eq = $func; break;
         case '__neq': $structure->operators->neq = $func; break;
         case '__lt': $structure->operators->lt = $func; break;
         case '__lte': $structure->operators->lte = $func; break;
         case '__gt': $structure->operators->gt = $func; break;
         case '__gte': $structure->operators->gte = $func; break;
         case '__add': $structure->operators->add = $func; break;
         case '__sub': $structure->operators->sub = $func; break;
         case '__mul': $structure->operators->mul = $func; break;
         case '__div': $structure->operators->div = $func; break;
         case '__mod': $structure->operators->mod = $func; break;
         case '__minus': $structure->operators->minus = $func; break;
         case '__plus': $structure->operators->plus = $func; break;
         case '__pre_inc': $structure->operators->preInc = $func; break;
         case '__pre_dec': $structure->operators->preDec = $func; break;
         }
      }


      if ( $trait !== null ) {
         foreach ( $trait->impls as $expectedImpl ) {
            foreach ( $expectedImpl->funcs as $func ) {
               if ( $impl->findFunc( $func->name ) === null &&
                  $func->body === null ) {
                  $this->user->diag( DIAG_ERR, $impl->pos,
                     'struct `%s` missing implementation for function `%s` ' .
                     'of trait `%s`', $structure->name, $func->name,
                     $impl->traitName );
                  $this->user->bail();
               }
            }
         }
         $impl->trait = $trait;
      }
      else {
         $structure->methods = $impl;
      }
   }

   public function checkTrait( TraitObj $trait ): void {
      $this->scope->enter();
      $this->checkTraitMembers( $trait );
      $this->scope->leave();
   }

   private function checkTraitMembers( TraitObj $trait ): void {
      foreach ( $trait->members as $member ) {
         $this->checkFuncPrototype( $member->func );
      }
   }

   /**
    * @param TypeParam[] $params
    */
   public function checkTypeParams( array $params ): void {
      $argPos = 0;
      foreach ( $params as $param ) {
         if ( $param->expectedType !== null ) {
            $param->type = $this->exprChecker->checkTypeExpr(
               $param->expectedType );
         }
         else {
            $param->type = new Type();
            $param->type->spec = TYPESPEC_STRUCT;
            $param->type->structure = $this->task->builtinModule->typeStruct;
            $param->type->placeholder = true;
            $param->type->typeParam = $param;
            $param->constant = true;
         }
         $this->scope->bind( $param->name, $param );
         $param->argPos = $argPos;
         ++$argPos;
      }
   }

   private function determineHomogeneous( \Structure $structure ): void {
      if ( count( $structure->members ) > 0 ) {
         $different = false;
         $firstMember = $structure->members[ 0 ];
         for ( $i = 1; $i < count( $structure->members ); ++$i ) {
            if ( ! $this->isSameType( $structure->members[ $i ]->type,
               $firstMember->type ) ) {
               $different = true;
               break;
            }
         }
         $structure->homogeneous = ( ! $different );
      }
   }

   public function checkFuncPrototype( \Func $func ): void {
      $this->func = $func;
      #$this->scope->bind( $func->name, $func );
      $this->scope->enter();
      $this->checkTypeParams( $func->typeParams );
      $this->checkParamList( $func );
      $this->checkReturnType( $func );
      $this->scope->leave();
      $this->func = null;
   }

   public function checkFunc( \Func $func ): void {
      $this->func = $func;
      $this->scope->enter();
/*
      foreach ( $func->params as $param ) {
         $this->scope->bind( $param->name, $param );
      }*/
      $this->checkFuncAttrs( $func );
      $this->checkTypeParams( $func->typeParams );
      $this->checkParamList( $func );
      $this->checkReturnType( $func );

      $this->checkFuncImpl( $func );
      if ( $func->body != null ) {
         $this->checkFuncBody( $func );
      }
      //$this->labelsMustBeDefined();
      if ( $func->virtual ) {
         // $this->vm->compileFunc( $func );
      }
      $func->resolved = true;
      $this->scope->leave();
      $this->func = null;
   }

   private function checkFuncAttrs( \Func $func ): void {
      foreach ( $func->attrs as $attr ) {
         switch ( $attr->name ) {
         case 'internal_func':
            $this->checkInternalFuncAttr( $func, $attr );
            break;
         case 'foreign':
            $func->foreign = true;
            break;
         default:
            $this->user->diag( DIAG_ERR, $attr->pos,
               "invalid function attribute: %s", $attr->name );
            $this->user->bail();
         }
      }
   }

   private function checkInternalFuncAttr( \Func $func, \Attr $attr ): void {
      if ( count( $attr->args ) != 1 ) {
         $this->user->diag( DIAG_ERR, $attr->pos,
            "`%s` attribute expects 1 argument", $attr->name );
         $this->user->bail();
      }
      $funcId = $this->checkArg( $attr->args[ 0 ] );
      if ( ! $this->isInstanceOf( $funcId,
         $this->typeChecker->createIntType() ) ) {
         $this->user->diag( DIAG_ERR, $attr->args[ 0 ]->pos,
            "argument 1 of `%s` attribute must be of Int type", $attr->name );
         $this->user->bail();
      }
      if ( ! $funcId->constant ) {
         $this->user->diag( DIAG_ERR, $attr->args[ 0 ]->pos,
            "argument 1 of `%s` attribute must be constant", $attr->name );
         $this->user->bail();
      }
      $func->builtin = $funcId->inhabitant;
      $func->evaluable = true;
      $func->internal = true;
   }

   private function checkParamList( \Func $func ): void {
      foreach ( $func->params as $param ) {
         $this->checkParam( $param );
         if ( $this->describe( $param->type ) === Desc::ERR ) {
            $func->malformed = true;
         }
      }
      if ( $func->argsParam != null ) {
         $this->checkArgsParam( $func );
      }
   }

   private function checkParam( \Param $param,
      bool $usePlaceHolder = false ): void {
   /*
      $binding = new \Let();
      $binding->pos = $param->pos;
      $binding->name = $param->name;
      $binding->param = $param;
      $this->scope->bind( $binding->name, $binding ); */
      if ( $param->expectedTypeExpr !== null ) {
         $type = $this->exprChecker->checkTypeExpr(
            $param->expectedTypeExpr );
         $param->type = $type;
         //$param->expectedValue = $value;
         /*
         if ( $this->describe( $type ) === Desc::STRUCT_TYPE ) {
            $value->type->structure = $value->inhabitant;
            $value->type->spec = TYPESPEC_STRUCT;
            $value->inhabitant = null;
         }
         */
      }
      else if ( $param->expectedType !== null ) {
         $type = $this->exprChecker->checkTypeExpr( $param->expectedType );
         //$param->expectedValue = $value;
         $param->type = $type;
         if ( $param->defaultArg !== null ) {
            $result = $this->exprChecker->checkExpr( $param->defaultArg  );
            if ( ! $this->isInstanceOf( $result, $param->type ) ) {
               $this->user->diag( DIAG_ERR, $param->pos,
                  'default argument type (`%s`) not compatible with ' .
                  'parameter type (`%s`)',
                  $this->presentType( $result->type ),
                  $this->presentType( $param->type ) );
               $this->user->bail();
            }
         }
      }
      else {
         if ( $param->defaultArg !== null ) {
            $result = $this->exprChecker->checkExpr( $param->defaultArg  );
            $param->type = $result->type;
            $param->type->value = null;
         }
         else {
            if ( $usePlaceHolder ) {
               $param->type = new Type();
               $param->type->placeholder = true;
               $param->constant = true;
            }
            else {
               $this->user->diag( DIAG_ERR, $param->pos,
                  'parameter `%s` missing a type', $param->name );
               $this->user->bail();
            }
         }
      }

      $value = new Value();
      $value->type = $param->type;
      //$value->type->value = $value;
      $param->value = $value;
      if ( $param->name !== '' ) {
         $binding = $this->scope->bind( $param->name, $param );
      }

      //$binding->value = $value;
   }

   private function checkArgsParam( \Func $func ): void {
      if ( $func->argsParam->expectedType != null ) {
         $func->argsParam->type = $this->exprChecker->checkTypeExpr(
            $func->argsParam->expectedType );
         if ( ! $this->implementsVarArgsTrait( $func->argsParam->type ) ) {
            $this->user->diag( DIAG_ERR, $func->argsParam->pos,
               "`%s` parameter type cannot store arguments",
               $func->argsParam->name );
            $this->user->bail();
         }
      }

      if  ( $func->argsParam->name !== '' ) {
         $this->scope->bind( $func->argsParam->name, $func->argsParam );
      }

      $func->variadic = true;
   }

   private function implementsVarArgsTrait( \Typing\Type $type ): bool {
      switch ( $this->describe( $type ) ) {
      case Desc::STRUCT:
         switch ( $type->structure->name ) {
         case 'Vec':
            return true;
         }
         break;
      }
      return false;
   }

   private function checkReturnType( \Func $func ): void {
      foreach ( $func->returnParams as $param ) {
         $this->checkParam( $param );
      }
      if ( $func->returnTypeExpr !== null ) {
         $func->returnType = $this->exprChecker->checkTypeExpr(
            $func->returnTypeExpr );

         if ( $this->describe( $func->returnType ) === Desc::ERR ) {
            $func->malformed = true;
         }

         if ( $this->describe( $func->returnType ) === Desc::STRUCT &&
            $func->returnType->structure->builtin === BUILTIN_STRUCTURE_NONE &&
            ! $func->returnType->structure->defined &&
            ! $func->returnType->borrowed ) {
            $this->user->diag( DIAG_ERR, $func->pos,
               "function `%s` returning undefined struct `%s`", $func->name,
               $func->returnType->structure->name );
            $this->user->bail();
         }
      }
      else {
         $func->returnType = $this->typeChecker->createVoidType();
      }
   }

   private function checkFuncBody( \Func $func ): void {
      $this->exprChecker->checkFuncBody( $func );
      if ( $func->body->evaluable ) {
         $func->evaluable = true;
      }
   }

   private function checkFuncImpl( \Func $func ): void {
      if ( ! is_null( $func->impl ) ) {
         $object = $this->scope->get( $func->impl->traitName )?->node;
         if ( $object instanceof TraitObj ) {
            if ( ! is_null( $func->impl->traitFuncName ) ) {
               $traitFunc = $object->findFunc( $func->impl->traitFuncName );
               if ( is_null( $traitFunc ) ) {
                  printf( "error: `%s` member not found in `%s` trait\n",
                     $func->impl->traitFuncName, $func->impl->traitName );
                  throw new \Exception();
               }

               if ( count( $func->params ) < 1 ) {
                  printf( "error: implementation function needs at least " .
                     "one parameter\n" );
                  throw new \Exception();
               }

               $param = $func->params[ 0 ];
               if ( $this->describe( $param->type ) !== Desc::PTR ) {
                  printf( "error: first parameter must be a pointer\n" );
                  throw new \Exception();
               }

               $result = new Value();
               $result->useParam( $param );
               $value = $result->deref();
               switch ( $this->describe( $value->type ) ) {
               case Desc::STRUCT:
                  $impl = $value->type->structure->getTraitImpl( $func->impl->traitName );
                  $memberImpl = $impl->setMemberImpl( $func->impl->traitFuncName, $func );
                  break;
               }
            }
         }
         else {
            printf( "error: `%s` trait not found\n", $func->impl->traitName );
            throw new \Exception();
         }
      }
   }

   public function checkGeneric( \Generic $generic ): void {
      $this->scope->enter();
      /*
      foreach ( $generic->params as $param ) {
         $this->checkParam( $param, true );
      }
      */
      $this->checkTypeParams( $generic->params );
      $value = $this->exprChecker->checkBlockStmt( $generic->body );
      $generic->value = $value;
      $generic->resolved = true;
      $this->scope->leave();
   }
}
