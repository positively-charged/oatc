<?php

declare( strict_types = 1 );

namespace Checking;

use Lexing\Position;
use Module;
use Typing\Type;
use const Typing\NATIVE_TYPE_CHAR;
use const Typing\NATIVE_TYPE_I16;
use const Typing\NATIVE_TYPE_I32;
use const Typing\NATIVE_TYPE_I64;
use const Typing\NATIVE_TYPE_I8;
use const Typing\NATIVE_TYPE_SCHAR;
use const Typing\NATIVE_TYPE_U16;
use const Typing\NATIVE_TYPE_U32;
use const Typing\NATIVE_TYPE_U64;
use const Typing\NATIVE_TYPE_U8;
use const Typing\NATIVE_TYPE_UCHAR;

class BuiltinModule {
   public Module $module;

   public \Structure $anyStruct;
   public \Structure $machineStruct;
   public \Structure $selfStruct;
   public \Structure $typeStruct;
   public \Structure $structStruct;
   public \Structure $intStruct;
   public \Structure $strStruct;
   public \Structure $boolStruct;
   public \Structure $neverStruct;
   public \Structure $errStruct;
   public \Structure $constPtrStruct;

   private Position $builtinPos;

   public function __construct() {
      $this->builtinPos = new Position();
      $this->builtinPos->file = 'builtin-module';
      $this->builtinPos->line = 0;
      $this->builtinPos->column = 0;
      $this->createModule();
   }

   private function createModule(): void {
      $module = new \Module();
      $module->path = 'builtin';

      $struct = new \Structure();
      $struct->name = 'Any';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      //$struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_ANY;
      $module->items[] = $struct;
      $this->anyStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Machine';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      //$struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_MACHINE;
      $module->items[] = $struct;
      $this->machineStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Self';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_SELF;
      //$module->items[] = $struct;
      $this->selfStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Int';
      $struct->pos = $this->builtinPos;
      //$struct->members[] = $member;
      $struct->visible = true;
      $struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_INT;
      $module->items[] = $struct;
      $this->intStruct = $struct;

      $member = new \StructureMember();
      $member->pos = $this->builtinPos;
      $member->name = 'value';
      $member->type = new Type();
      $member->type->spec = TYPESPEC_INT64;
      $member->builtin = \BuiltinStructMember::VALUE;
      $member->mutable = true;
      $struct->members[] = $member;

      $param = new \Param();
      $param->name = 'self';
      $param->type = new Type();
      $param->type->spec = TYPESPEC_STRUCT;
      $param->type->structure = $struct;
      $param->type->borrowed = true;
      $param->type->unchecked = true;

      $returnType = new Type();
      $returnType->structure = $struct;
      $returnType->spec = TYPESPEC_STRUCT;
      $returnType->borrowed = true;

      $func = new \Func();
      $func->name = 'unwrap';
      $func->builtin = \Func::BUILTIN_INT_UNWRAP;
      $func->params[] = $param;
      $func->returnType = $returnType;
      $func->resolved = true;

      $impl = new \Implementation();
      $struct->methods = $impl;
      $struct->methods->funcTable[ 'unwrap' ] = $func;



      $struct = new \Structure();
      $struct->name = 'Bool';
      $struct->pos = $this->builtinPos;
      //$struct->members[] = $member;
      $struct->visible = true;
      //$struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_BOOL;
      $module->items[] = $struct;
      $this->boolStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Never';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_NEVER;
      $module->items[] = $struct;
      $this->neverStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Err';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_ERR;
      $module->items[] = $struct;
      $this->errStruct = $struct;

      $struct = new \Structure();
      $struct->name = 'Ptr';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_PTR;
      $module->items[] = $struct;

      $struct = new \Structure();
      $struct->name = 'ConstPtr';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_CONST_PTR;
      $module->items[] = $struct;
      $this->constPtrStruct = $struct;

      $param = new \TypeParam();
      $param->name = 'T';
      $param->pos = $this->builtinPos;
      $struct->params[] = $param;

      $this->createIntType( $module, 'i8', NATIVE_TYPE_I8 );
      $this->createIntType( $module, 'i16', NATIVE_TYPE_I16 );
      $this->createIntType( $module, 'i32', NATIVE_TYPE_I32 );
      $this->createIntType( $module, 'i64', NATIVE_TYPE_I64 );
      $this->createIntType( $module, 'u8', NATIVE_TYPE_U8 );
      $this->createIntType( $module, 'u16', NATIVE_TYPE_U16 );
      $this->createIntType( $module, 'u32', NATIVE_TYPE_U32 );
      $this->createIntType( $module, 'u64', NATIVE_TYPE_U64 );
      $this->createIntType( $module, 'char', NATIVE_TYPE_CHAR );
      $this->createIntType( $module, 'schar', NATIVE_TYPE_SCHAR );
      $this->createIntType( $module, 'uchar', NATIVE_TYPE_UCHAR );
      $this->createStrType( $module );

      //$this->createVecType( $module );
      $this->createTypeStruct( $module );

      $this->module = $module;
   }

   private function createTypeStruct( Module $module ): void {
      $struct = new \Structure();
      $struct->name = 'Type';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_TYPE;
      $module->items[] = $struct;
      $this->typeStruct = $struct;

      $member = new \StructureMember();
      $member->pos = $this->builtinPos;
      $member->name = 'name';
      $member->type = new Type();
      $member->type->spec = TYPESPEC_STRUCT;
      $member->type->structure = $this->strStruct;
      $struct->members[] = $member;

      $member = new \StructureMember();
      $member->pos = $this->builtinPos;
      $member->name = 'borrowed';
      $member->type = new Type();
      $member->type->spec = TYPESPEC_STRUCT;
      $member->type->structure = $this->boolStruct;
      $struct->members[] = $member;
   }

   private function createStructStruct( Module $module ): void {
      $struct = new \Structure();
      $struct->name = 'Struct';
      $struct->pos = $this->builtinPos;
      $struct->visible = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_STRUCT;
      $module->items[] = $struct;
      $this->structStruct = $struct;
   }

   private function createStrType( Module $module ): void {
      $struct = new \Structure();
      $struct->name = 'Str';
      $struct->pos = $this->builtinPos;
      //$struct->members[] = $member;
      $struct->visible = true;
      //$struct->defined = true;
      $struct->resolved = true;
      $struct->builtin = BUILTIN_STRUCTURE_STR;
      $module->items[] = $struct;
      $this->strStruct = $struct;

      $param = new \Param();
      $param->name = 'self';
      $param->type = new Type();
      $param->type->spec = TYPESPEC_STRUCT;
      $param->type->structure = $struct;
      $param->type->borrowed = true;

      $returnType = new Type();
      $returnType->structure = $this->constPtrStruct;
      $returnType->spec = TYPESPEC_STRUCT;
      $arg = new Type();
      $arg->structure = $this->intStruct;
      $arg->spec = TYPESPEC_STRUCT;
      $returnType->args[] = $arg;

      $func = new \Func();
      $func->name = 'ptr';
      $func->builtin = \Func::BUILTIN_STR_PTR;
      $func->params[] = $param;
      $func->returnType = $returnType;
      $func->resolved = true;

      $impl = new \Implementation();
      $struct->methods = $impl;
      $struct->methods->funcTable[ 'ptr' ] = $func;
   }

   private function createIntType( Module $module, string $name,
      int $representation ): void {
      $alias = new \TypeAlias();
      $alias->pos = $this->builtinPos;
      $alias->name = $name;
      $alias->type = new Type();
      $alias->type->spec = TYPESPEC_STRUCT;
      $alias->type->structure = $this->intStruct;
      $alias->type->representation = $representation;
      #$alias->type->borrowed = true;
      $alias->visible = true;
      $module->items[] = $alias;
   }

   private function createVecType( Module $module ): void {
      $struct = new \Structure();
      $struct->pos = $this->builtinPos;
      $struct->name = 'Vec';
      $struct->builtin = BUILTIN_STRUCTURE_VEC;
      $struct->visible = true;
      $struct->defined = true;
      $struct->resolved = true;
      $module->items[] = $struct;
   }
}
