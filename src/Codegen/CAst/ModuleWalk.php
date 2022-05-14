<?php

declare( strict_types = 1 );

namespace Codegen\Cast;

use \Module;
use \Func;
use \BlockStmt;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description;
use Typing\Description as Desc;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\Type;
use const Typing\NATIVE_TYPE_CHAR;
use const Typing\NATIVE_TYPE_I16;
use const Typing\NATIVE_TYPE_I32;
use const Typing\NATIVE_TYPE_I64;
use const Typing\NATIVE_TYPE_I8;
use const Typing\NATIVE_TYPE_U16;
use const Typing\NATIVE_TYPE_U32;
use const Typing\NATIVE_TYPE_U64;
use const Typing\NATIVE_TYPE_U8;

const STR_TABLE_VAR = 'strtbl';

class ModuleWalk {
   use DescriberUsage;
   use InstanceCheckerUsage;

   private CodegenTask $codegenTask;
   private Module $module;
   private CTranslationUnit $unit;
   private ScopeList $scopeList;

   public function __construct(
      private Describer $typeDescriber,
      private InstanceChecker $instanceChecker,
      private Presenter $presenter,
      private \Task $task, Module $module,
      CodegenTask $codegenTask,
      CTranslationUnit $unit ) {
      $this->module = $module;
      $this->codegenTask = $codegenTask;
      $this->unit = $unit;
      $this->scopeList = new ScopeList();
   }

   public function visitModule(): void {
      $unit = $this->unit;
      $this->createStringTable( $unit, $this->module );
      $this->createObjects();
      $this->fillStructs();
      $this->createCFuncs( $unit );
      $this->createCVars( $unit );

      //$this->createObjects();
      //$this->fillStructs();
      //$this->fillFuncs();
      //$this->fillVars();
      $this->fillFuncs();
   }

   private function createStringTable( CTranslationUnit $unit,
      Module $module ): void {
      $unit->strings = $this->task->strings;
      /*
      if ( count( $this->task->strings ) > 0 ) {
         $var = new CVar();
         $var->static = true;
         $var->const = true;
         $var->type->spec = SPEC_CHAR;
         $var->name = STR_TABLE_VAR;
         $var->type->pointers[] = new CPointer();
         $var->dims[] = count( $this->task->strings );
         $unit->vars[] = $var;
         $initz = new CBracedInitializer();
         foreach ( $this->task->strings as $string ) {
            $literal = new CStringLiteral();
            $literal->value = $string;
            $expr = new CExpr();
            $expr->root = $literal;
            $initz->children[] = $expr;
         }
         $var->initializer = $initz;
      }
      */
   }

   private function createObjects(): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Structure ) {
            if ( $item->trait ) {
               $this->createStruct( $item->name );
            }
            else {
               #if ( ! $this->isZeroSizedStruct( $item ) ) {
                  $this->createStruct( $item->name );
               #}
            }
         }
         else if (
            $item instanceof \Enumeration ||
            $item instanceof \TraitObj ) {
            $this->createStruct( $item->name );
         }
         else if ( $item instanceof Func ) {
            $this->createFunc( $item );
         }
      }

      $id = 0;
      foreach ( $this->module->tuples as $structure ) {
         if ( ! $this->isZeroSizedStruct( $structure ) ) {
            $this->createStruct( sprintf( 'Tuple%d', $id ) );
            ++$id;
         }
      }

      $this->createStruct( $this->task->builtinModule->intStruct->name );
   }

   private function createStruct( string $name ): void {
      $struct = new CStruct();
      $struct->name = $name;
      $this->unit->typeSymbols[ $struct->name ] = $struct;
   }

   private function createFunc( \Func $func ): void {
      $cFunc = new CFunc();
      $cFunc->name = $func->name;
      $this->unit->symbols[ $cFunc->name ] = $cFunc;
      $this->codegenTask->funcsToCfuncs[ $cFunc->name ] = $cFunc;
   }

   public function getCStruct( \Structure $structure ): CStruct {
      return $this->unit->structs->get( $structure->index );
   }

   private function fillStructs(): void {
      $id = 0;
      foreach ( $this->module->tuples as $structure ) {
         if ( ! $this->isZeroSizedStruct( $structure ) ) {
            $name = sprintf( 'Tuple%d', $id );
            #$structure->name = $name;
            $this->fillCStructForStruct( $structure,
               $this->unit->typeSymbols[ $name ] );
            ++$id;
         }
      }

      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Enumeration ) {
            $this->fillStructForEnum( $item,
               $this->unit->typeSymbols[ $item->name ] );
         }
         else if ( $item instanceof \Structure ) {
            if ( $item->trait ) {
               $this->fillTraitCStruct( $item,
                  $this->unit->typeSymbols[ $item->name ] );
            }
            else {
               if ( ! $this->isZeroSizedStruct( $item ) ) {
                  $this->fillCStructForStruct( $item,
                     $this->unit->typeSymbols[ $item->name ] );
               }
            }
         }
      }

      $this->fillCStructForStruct( $this->task->builtinModule->intStruct,
         $this->unit->typeSymbols[ $this->task->builtinModule->intStruct->name ] );

/*
      $set = new StructSet();
      foreach ( $this->unit->structs as $structure ) {
         $set->add( $structure );
      }
*/
   }

   private function fillStructForEnum( \Enumeration $enumeration,
      CStruct $struct ): void {
      $structMember = new CStructMember();
      $structMember->name = 'tag';
      $structMember->type = new CType( spec: SPEC_INT32 );
      $this->appendMember( $struct, $structMember );
      //if ( $this->doesEnumHaveCargo( $enumeration ) ) {
         $options = new CStruct();
         $options->union = true;
         $member = new CStructMember();
         $member->name = 'u';
         $member->type = new CType( spec: SPEC_NESTED_STRUCT,
            struct: $options );
         $this->appendMember( $struct, $member );
         foreach ( $enumeration->body as $enumerator ) {
            $member = new CStructMember();
            $member->type = $this->createCType( $enumerator->type );
            $this->appendMember( $options, $member );
            $enumerator->index = $member->index;

            /*
            if ( count( $enumerator->params ) > 0 ) {
               $member = new CStructMember();
               $member->type = new CType( spec: SPEC_NESTED_STRUCT,
                  struct: new CStruct() );
               $this->appendMember( $options, $member );
               $enumerator->id = $member->index;
               $field = $member->type->struct;
               foreach ( $enumerator->params as $param ) {
                  $type = $this->createCType( $param->type );
                  $member = new CStructMember();
                  $member->name = $param->name;
                  $member->type = $type;
                  $this->appendMember( $field, $member );
               }
            }
            */
         }
      //}

      $cStruct = $this->unit->structs->add( $struct );
      $cStruct->originalName = $struct->name;
      $struct->index = $cStruct->index;
      $enumeration->index = $struct->index;
   }

   private function appendMember( CStruct $struct,
      CStructMember $member ): void {
      $member->index = count( $struct->members );
      $struct->members[] = $member;
   }

   private function doesEnumHaveCargo( \Enumeration $enumeration ): bool {
      foreach ( $enumeration->body as $enumerator ) {
         if ( count( $enumerator->params ) > 0 ) {
            return true;
         }
      }
      return false;
   }

   private function fillCStructForStruct( \Structure $struct,
      CStruct $cStruct ): void {
      if ( $struct->homogeneous ) {
         $type = $this->createCType( $struct->members[ 0 ]->type );
         $member = new CStructMember();
         $member->type = $type;
         $member->dims = [ count( $struct->members ) ];
         $cStruct->homogeneous = true;
         $this->appendMember( $cStruct, $member );
      }
      else {
         foreach ( $struct->members as $member ) {
            $this->fillCStructMember( $cStruct, $member );
         }
      }

      $cStruct = $this->unit->structs->add( $cStruct );
      $cStruct->originalName = $struct->name;
      $cStruct->refCounted = true;
      $struct->index = $cStruct->index;
   }

   private function fillCStructMember( CStruct $struct,
      \StructureMember $member ): void {
      $type = $this->createCType( $member->type );
      $structMember = new CStructMember();
      $structMember->type = $type;
      $this->appendMember( $struct, $structMember );
   }

   public function createCType( Type $type ): CType {
      $cType = new CType();

      switch ( $this->typeDescriber->describe( $type ) ) {
      case Desc::INT:
      case Desc::UNCHECKED_INT:
         $cType->spec = SPEC_INT64;
         switch ( $type->representation ) {
         case NATIVE_TYPE_I8: $cType->spec = SPEC_INT8; break;
         case NATIVE_TYPE_I16: $cType->spec = SPEC_INT16; break;
         case NATIVE_TYPE_I32: $cType->spec = SPEC_INT32; break;
         case NATIVE_TYPE_I64: $cType->spec = SPEC_INT64; break;
         case NATIVE_TYPE_U8: $cType->spec = SPEC_UINT8; break;
         case NATIVE_TYPE_U16: $cType->spec = SPEC_UINT16; break;
         case NATIVE_TYPE_U32: $cType->spec = SPEC_UINT32; break;
         case NATIVE_TYPE_U64: $cType->spec = SPEC_UINT64; break;
         case NATIVE_TYPE_CHAR: $cType->spec = SPEC_CHAR; break;
         default: throw new \Exception();
         }

         if ( $type->borrowed ) {
            $pointer = new CPointer();
            $pointer->constant = ( ! $type->mutable );
            $cType->pointers[] = $pointer;
         }

         break;
      case Desc::INT64:
         $cType->spec = SPEC_INT64;
         break;
      case Desc::BOOL:
         $cType->spec = SPEC_BOOL;
         break;
      case Desc::STR:
         $cType->spec = SPEC_CHAR;
         $cType->pointers[] = new CPointer();
         $cType->const = true;
         break;
      case Desc::ENUM:
         $cType->spec = SPEC_STRUCTPTR;
         $cType->struct = $this->unit->structs->get(
            $type->enumeration->index );
         break;
      case Desc::STRUCT:
      case Desc::STRUCT_TYPE:
         switch ( $type->structure->builtin ) {
         case BUILTIN_STRUCTURE_NONE:
         case BUILTIN_STRUCTURE_MACHINE:
            // All struct types are passed by reference.
            $cType->spec = SPEC_STRUCTPTR;
            $cType->struct = $this->getCStruct( $type->structure );
            break;
         default:
            throw new \Exception();
         }
         break;
         /*
      case Desc::TRAIT:
         $cType->spec = SPEC_STRUCT;
         $cType->struct = $this->codegenTask->structsToCStructs[
            $type->trait->name ];
         break;
         */
      case Desc::PTR:
         $constant = false;
         if ( $type->structure->builtin === BUILTIN_STRUCTURE_CONST_PTR ) {
            $constant = true;
         }
         $pointers = [];
         while ( $this->describe( $type ) === Desc::PTR ) {
            $pointers[] = new CPointer();
            $type = $type->args[ 0 ];
         }
         $cType = $this->createCType( $type );
         $cType->pointers = $pointers;
         $cType->const = $constant;
         break;
      case Desc::VALUE:
         return $this->createCType( $type->value->type );
      case Desc::NEVER:
      case Desc::VOID:
         break;
      default:
      var_dump( $this->describe( $type ) );
         throw new \Exception();
      }
      return $cType;
   }

   private function fillTraitCStruct( \Structure $struct,
      CStruct $cStruct ): void {
      $this->fillStructForTrait( $this->unit, $struct, $cStruct );

      $cStruct = $this->unit->structs->add( $cStruct );
      $cStruct->originalName = $struct->name;
      $cStruct->trait = true;
      $struct->index = $cStruct->index;
   }

   private function fillStructForTrait( CTranslationUnit $unit,
      \Structure $trait, CStruct $struct ): void {
      $struct->name = sprintf( '%sInterface', $trait->name );

      $param = new CStruct();
      $param->name = $trait->name;
      $param->refCounted = false;
      $structMember = new CStructMember();
      $structMember->name = 'interface';
      $structMember->type = new CType( spec: SPEC_STRUCTPTR,
         struct: $struct );
      $this->appendMember( $param, $structMember );

      $structMember = new CStructMember();
      $structMember->name = 'object';
      $structMember->type = new CType( spec: SPEC_VOID );
      $structMember->type->pointers[] = new CPointer();
      $this->appendMember( $param, $structMember );

      $unit->structs->add( $param );

      foreach ( $trait->members as $member ) {
         $this->createTraitMember( $unit, $struct, $member );
      }

      foreach ( $trait->impls as $impl ) {
         foreach ( $impl->funcs as $func ) {
            $this->createTraitMethod( $unit, $struct, $func );
         }
      }
      //$this->codegenTask->structsToCStructs[ $trait->name ] = $param;
      //$this->codegenTask->structsToCStructs[ $struct->name ] = $struct;
      $unit->structs->add( $struct );
   }

   private function createTraitMember( CTranslationUnit $unit,
      CStruct $struct, \StructureMember $member ): void {
      $structMember = new CStructMember();
      $structMember->name = $member->name;
      $structMember->type = $this->createCType( $member->type );
      //$structMember->type->pointers[] = new CPointer();
      $structMember->traitMember = true;
      $this->appendMember( $struct, $structMember );
      /*
      $cFunc = $this->createCFuncFromFunc( $unit, $member->func );
      $structMember = new CStructMember();
      $structMember->name = $member->func->name;
      $structMember->type = $cFunc->returnType;
      //$structMember->type->params = $cFunc->params;
      $this->appendMember( $struct, $structMember );
      $declarator = new CDeclarator();
      $params = new CParamList();
      $params->params = $cFunc->params;

      $object = new CParam();
      $object->type = new CType();
      array_unshift( $params->params, $object );

      $declarator->params = $params;
      $declarator->name = $member->func->name;
      $parens = new CDeclarator();
      $parens->name = $member->func->name;
      $parens->pointers[] = new CPointer;
      $declarator->parens = $parens;
      $structMember->declarator = $declarator;
      */
   }

   private function createTraitMethod( CTranslationUnit $unit,
      CStruct $struct, \Func $func ): void {
      $cFunc = $this->createCFuncFromFunc( $unit, $func );
      $structMember = new CStructMember();
      $structMember->name = $func->name;
      $structMember->type = $cFunc->returnType;
      //$structMember->type->params = $cFunc->params;
      $structMember->traitMethod = $cFunc;
      $this->appendMember( $struct, $structMember );
      /*
      $declarator = new CDeclarator();
      $params = new CParamList();
      $params->params = $cFunc->params;

      $object = new CParam();
      $object->type = new CType();
      array_unshift( $params->params, $object );

      $declarator->params = $params;
      $declarator->name = $member->func->name;
      $parens = new CDeclarator();
      $parens->name = $member->func->name;
      $parens->pointers[] = new CPointer;
      $declarator->parens = $parens;
      $structMember->declarator = $declarator;
      */
   }

   private function createCVars( CTranslationUnit $unit ): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Structure ) {
            $this->createImpls( $unit, $item );
         }
      }
   }

   private function createImpls( CTranslationUnit $unit,
      \Structure $structure ): void {
      foreach ( $structure->impls as $impl ) {
         if ( $impl->traitName != '' ) {
            $this->createImpl( $unit, $structure->name, $impl );
         }
      }
   }

   private function createImpl( CTranslationUnit $unit, string $name,
      \Implementation $impl ): void {
      $var = new CVar();
      $var->name = $name . 'x' . $impl->traitName . 'Impl';
      $var->spec = SPEC_STRUCT;
      //$var->struct = $this->codegenTask->structsToCStructs[ $impl->traitName . 'Interface' ];

      $initz = new CBracedInitializer();
      foreach ( $impl->funcs as $func ) {
         $usage = new CNameUsage();
         $usage->name = sprintf( '%sx%sx%s', $name, $impl->traitName,
            $func->name );
         $expr = new CExpr();
         $expr->root = $usage;
         $initz->children[] = $expr;
      }


      $var->initializer = $initz;

      $unit->vars[] = $var;
   }

   private function createCFuncs( CTranslationUnit $unit ): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof \Func ) {
            if ( ! $item->virtual ) {
               $this->createCFunc( $unit, $item, visible: $item->visible );
            }
         }
         else if ( $item instanceof \Constant ) {
            if ( $item->value->value instanceof Func ) {
               if ( ! $item->value->value->virtual ) {
                  $this->createCFunc( $unit, $item->value->value,
                     name: $item->name, visible: $item->visible );
               }
            }
         }
         else if ( $item instanceof \Enumeration ) {
         /*
            if ( $item->hasRefParam ) {
               $this->createEnumCleanupFunc( $item );
            }
         */
         }
         else if ( $item instanceof \Structure ) {
            $this->createMethods( $unit, $item );
         }
      }
   }

   private function createMethods( CTranslationUnit $unit,
      \Structure $structure ): void {

      foreach ( $structure->impls as $impl ) {
         foreach ( $impl->funcs as $func ) {
            if ( ! $func->virtual ) {
               $name = sprintf( '%sx%sx', $structure->name,
                  $impl->traitName );
               $this->createCFunc( $unit, $func, $name, true,
                  visible: $func->visible );
            }
         }
      }

      // Create cleanup function for struct.
      /*
      if ( $this->structHasRefMembers( $structure ) ) {
         $this->createStructCleanupFunc( $structure );
      }
      */

      /*
         if ( $item->methods != null ) {
            foreach ( $item->methods->funcs as $func ) {
               if ( ! $func->virtual ) {
                  $this->createCFunc( $unit, $func, $item->name . "x" );
               }
            }
         }
      */
   }

   private function createEnumCleanupFunc( \Enumeration $enum ): void {
      $func = new CFunc();
      $func->name = sprintf( '%sxCleanup', $enum->name );

      // Object parameter.
      /*
      $param = new CParam();
      $param->name = 'object';
      $param->spec = SPEC_STRUCTPTR;
      //$param->struct = $this->codegenTask->structsToCStructs[
      //   $enum->name ];
      array_push( $func->params, $param );
      */

      $body = new CCompoundStmt();
      $func->body = $body;

      // Cleanup members.
      $cleanupStmt = new CEnumCleanupStmt();
      $cleanupStmt->param = $param;
      array_push( $body->items, $cleanupStmt );
      foreach ( $enum->body as $field => $enumerator ) {
         if ( $enumerator->hasRefParam ) {
            $case = new CEnumCleanupCase();
            $case->tag = $enumerator->value;
            $case->field = sprintf( 'm%d', $field );
            array_push( $cleanupStmt->cases, $case );
            foreach ( $enumerator->params as $enumeratorParam ) {
               if ( $enumeratorParam->isRefType ) {
                  $stmt = new CCleanupStmt();
                  $stmt->object = sprintf( 'object->u.m%d.%s', $field,
                     $enumeratorParam->name );
                  $stmt->struct = $this->getStructForType(
                     $enumeratorParam->type );
                  array_push( $case->params, $stmt );
               }
            }
         }
      }
      /*
            $stmt = new CFreeStmt();
            $stmt->object = 'object';
            array_push( $body->items, $stmt );
      */

      array_push( $this->unit->funcs, $func );
      $param->struct->cleanupFunc = $func;
   }

   private function getStructForType( Type $type ): CStruct {
      switch ( $type->spec ) {
      case TYPESPEC_STRUCT:
         return $this->getCStruct( $type->structure );
      case TYPESPEC_ENUM:
         //return $this->codegenTask->structsToCStructs[ $type->enumeration->name ];
      }
   }

   private function createStructCleanupFunc( \Structure $structure ): void {
      $func = new CFunc();
      $func->name = sprintf( '%sxCleanup', $structure->name );

      // Object parameter.
      /*
      $param = new CParam();
      $param->name = 'object';
      $param->spec = SPEC_STRUCTPTR;
      $param->struct = $this->getCStruct( $structure );
      array_push( $func->params, $param );
      */

      $body = new CCompoundStmt();
      $func->body = $body;

      // Cleanup members.
      foreach ( $structure->members as $member ) {
         if ( $member->type->spec == TYPESPEC_STRUCT &&
            ! $this->isPrimitiveStruct( $member->type->structure ) ) {
            $this->cleanupStructMember( $func, $member );
         }
      }

      // Free object.
      /*
      $nameUsage = new CNameUsage();
      $nameUsage->name = 'object';
      $stmt = $this->cleanupObject( null, $nameUsage );
      array_push( $body->items, $stmt );*/
      $stmt = new CFreeStmt();
      $stmt->object = 'object';
      array_push( $body->items, $stmt );

      array_push( $this->unit->funcs, $func );
      $param->struct->cleanupFunc = $func;
   }

   private function cleanupStructMember( CFunc $func,
      \StructureMember $member ): void {
      $compoundStmt = new CCompoundStmt();
      $compoundStmt->groupOnly = true;
      $func->body->items[] = $compoundStmt;

      $cleanupStmt = new CCleanupStmt();
      $cleanupStmt->object = sprintf( 'object->%s', $member->name );
      $cleanupStmt->struct = $this->getCStruct( $member->type->structure );
      $compoundStmt->items[] = $cleanupStmt;
   }

   private function structHasRefMembers( \Structure $structure ): bool {
      foreach ( $structure->members as $member ) {
         if ( $member->type->spec == TYPESPEC_STRUCT &&
            ! $this->isPrimitiveStruct( $member->type->structure ) ) {
            return true;
         }
      }
      return false;
   }

   private function isPrimitiveStruct( \Structure $structure ): bool {
      switch ( $structure->name ) {
      case 'Int':
      case 'Bool':
         return true;
      default:
         return false;
      }
   }

   private function createCFunc( CTranslationUnit $unit, \Func $func,
      string $prefix = '', bool $isTraitFunc = false,
      string $name = '', bool $visible = false ): void {
      if ( ! $func->malformed ) {
         $cFunc = $this->createCFuncFromFunc( $unit, $func, $prefix,
            $isTraitFunc, $name, $visible );
         $unit->funcs[] = $cFunc;
         $this->codegenTask->funcsToCfuncs[ $cFunc->name ] = $cFunc;
      }
   }

   private function createCFuncFromFunc( CTranslationUnit $unit, \Func $func,
      string $prefix = '', bool $isTraitFunc = false,
      string $name = '', bool $visible = false ): CFunc {
      if ( $name === '' ) {
         $name = $func->name;
      }
      $name = $prefix . $name;
      $cFunc = new CFunc();
      $cFunc->name = $name;
      $cFunc->static = ( $visible == false );

      if ( $func->variadic && $func->foreign ) {
         $cFunc->variadic = true;
      }

      // Return type:
      if ( ! $this->isVoid( $func->returnType ) ) {
         $returnType = $this->createParamType( $func->returnType );
         $cFunc->returnType = $returnType;
      }

/*
      if ( $isTraitFunc ) {
         $cParam = new CParam();
         $cParam->name = 'object';
         $cParam->type = new CType( spec: SPEC_VOID,
            pointers: [ new CPointer() ] );
         $func->params[ 0 ]->cParam = $cParam;
         $cFunc->params[] = $cParam;

         $var = new CVar();
         $var->name = $cParam->name;
         $var->type = $cParam->type;
         $cParam->var = $var;

         $cFunc->selfParam = $cParam;
      }
*/

      foreach ( $func->params as $i => $param ) {
      /*
         if ( $isTraitFunc && $i == 0 ) {
            continue;
         }
      */
         $type = $this->createParamType( $param->type );
         $cParam = new CParam();
         $cParam->name = $param->name;
         $cParam->type = $type;
         $cParam->index = count( $cFunc->params ) + 1;
         $cFunc->params[] = $cParam;

         $var = new CVar();
         $var->name = $cParam->name;
         $var->type = $type;
         $cParam->var = $var;

         $param->cParam = $cParam;

      }
      return $cFunc;
   }

   private function createParamType( Type $type ): CType {
      switch ( $this->describe( $type ) ) {
      case Description::ENUM:
         $this->addEnumType( $type->enumeration );
         break;
      }
      $type = $this->createCType( $type );
      return $type;
   }

   public function addEnumType( \Enumeration $enumeration ): CStruct {
      $this->createStruct( $enumeration->name );
      $this->fillStructForEnum( $enumeration,
         $this->unit->typeSymbols[ $enumeration->name ] );
      return $this->unit->typeSymbols[ $enumeration->name ];
   }

   public function addStructType( \Structure $structure ): CStruct {
      $cStruct = new CStruct();
      //$cStruct->name = $name;
      //$this->unit->typeSymbols[ $struct->name ] = $struct;

      $this->fillCStructForStruct( $structure, $cStruct );
      return $cStruct;
   }

   private function fillFuncs(): void {
      foreach ( $this->module->items as $item ) {
         if ( $item instanceof Func ) {
            if ( ! $item->virtual ) {
               $this->visitFunc( $this->unit, $item );
            }
         }
         else if ( $item instanceof \Constant ) {
            if ( $item->value->value instanceof Func ) {
               if ( ! $item->value->value->virtual ) {
                  $this->visitFunc( $this->unit, $item->value->value,
                     name: $item->name );
               }
            }
         }
         else if ( $item instanceof \Structure ) {
            $this->fillMethods( $item );
         }
      }
   }

   private function visitFunc( CTranslationUnit $unit, Func $func,
      string $prefix = '', string $name = '' ): void {
      if ( $func->body !== null ) {
         if ( $name === '' ) {
            $name = $func->name;
         }
         $name = $prefix . $name;
         $this->codegenTask->funcsToCfuncs[ $name ]->body =
            $this->visitBlockStmt( $func, $name );
      }
   }

   private function fillMethods( \Structure $structure ): void {
      foreach ( $structure->impls as $impl ) {
         foreach ( $impl->funcs as $func ) {
            if ( ! $func->virtual ) {
               $this->visitFunc( $this->unit, $func,
                  $structure->name . "x" . $impl->traitName . 'x',
                  $func->name );
               #$this->visitTraitFunc( $this->unit, $func, $item,
               #   $item->name . "x" . $impl->traitName . 'x' );
            }
         }
      }
      /*
      if ( $item->methods != null ) {
         foreach ( $item->methods->funcs as $func ) {
            if ( ! $func->virtual ) {
               $this->visitFunc( $unit, $func, $item->name . "x" );
            }
         }
      }*/
   }

   private function visitTraitFunc( CTranslationUnit $unit, Func $func,
      \Structure $structure, string $prefix = '' ): void {
      if ( $func->body !== null ) {
         $name = $prefix . $func->name;
         $this->codegenTask->funcsToCfuncs[ $name ]->body =
            $this->visitBlockStmt( $func, $name );

         $object = new CNameUsage();
         $object->name = 'object';
         $var = new CVar();
         $var->name = $func->params[ 0 ]->name;
         $var->spec = SPEC_STRUCTPTR;
         $var->struct = $this->getCStruct( $structure );
         $assignment = new CAssignment();
         $assignment->lside = $var;
         $assignment->rside = $object;
         $expr = new CExpr();
         $expr->root = $assignment;
         $stmt = new CExprStmt();
         $stmt->expr = $expr;
         array_unshift( $this->codegenTask->funcsToCfuncs[ $name ]->body->items,
            $stmt );

         $cFunc = $this->codegenTask->funcsToCfuncs[ $name ];
         $cFunc->params[ 0 ]->altName = $var->name;
      }
   }

   private function visitBlockStmt( Func $func, string $name ): CNode {
      $frame = new StackFrame();
      $walk = new ExprWalker( $this->typeDescriber, $this->instanceChecker,
         $this->presenter,
         $this, $frame,
         $this->codegenTask, $this->unit, $this->scopeList );
      $cFunc = $this->codegenTask->funcsToCfuncs[ $name ];
      $cStmt = $walk->visitTopBlockStmt( $func, $cFunc );
      $cStmt->vars = $frame->vars;
      return $cStmt;
   }
}
