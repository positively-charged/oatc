<?php

declare( strict_types = 1 );

use Checking\Value;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description as Desc;
use Typing\Type;

class Slot {
   public Type $type;
   public mixed $value = null;
}

class Binding {
   public string $name;
   public ?Slot $slot;
   public ?Type $expectedType;
   public ?Value $value;
   public ?Node $node;
   public ?Module $module;
   public bool $conditional;
   public bool $initialized;
   public bool $rebindable;

   public function __construct() {
      $this->slot = null;
      $this->expectedType = null;
      $this->value = null;
      $this->node = null;
      $this->module = null;
      $this->conditional = false;
      $this->initialized = false;
      $this->rebindable = false;
   }
}

const SCOPE_NORMAL = 0;
const SCOPE_CONDITION = 1;
const SCOPE_AND = 2;
const SCOPE_OR = 3;
const SCOPE_RSIDE = 4;

function scopeName( $type ): string {
   switch ( $type ) {
   case SCOPE_NORMAL: return "Normal";
   case SCOPE_AND: return "And";
   case SCOPE_CONDITION: return "Affirmative";
   case SCOPE_OR: return "Or";
   case SCOPE_RSIDE: return "Rside";
   }
}

class ScopeFloor {
   /**
    * @var Binding[]
    */
   public array $bindings;
   public int $type;

   public function __construct( int $type = SCOPE_NORMAL ) {
      $this->bindings = [];
      $this->type = $type;
   }

   public function bind( string $name, Node $object ): void {
      if ( ! array_key_exists( $name, $this->bindings ) ) {
         $binding = new Binding();
         $binding->name = $name;
         //$binding->slot = new Slot();
         //$binding->slot->value = $object;
         $binding->node = $object;
         $this->bindings[ $name ] = $binding;
      }
      else {
         printf( "error: name `%s` already used\n", $name );
         throw new Exception();
      }
   }

   public function createBinding( string $name ): Binding {
      if ( ! array_key_exists( $name, $this->bindings ) ) {
         $binding = new Binding();
         $binding->name = $name;
         $this->bindings[ $name ] = $binding;
         return $binding;
      }
      else {
         throw new \Exception();
      }
   }

   public function get( string $name ): ?Binding {
      if ( array_key_exists( $name, $this->bindings ) ) {
         return $this->bindings[ $name ];
      }
      return null;
      /*
      $binding = $this->getInModule( $name );
      if ( $binding == null ) {
         foreach ( $this->linkedScopes as $scope ) {
            $binding = $scope->get( $name );
            if ( $binding != null ) {
               return $binding;
            }
         }
      }
      return $binding;
      */
   }
}

class Scope {
   use DescriberUsage;

   private User $user;
   private Module $module;
   /**
    * @var ScopeFloor[]
    */
   public array $floors;
   private ScopeFloor $activeFloor;

   public function __construct( User $user, Module $module,
      private Describer $typeDescriber ) {
      $this->user = $user;
      $this->module = $module;
      $this->floors = [];
      $this->enter();
   }

   public function changeModule( Module $module ): Module {
      $prevModule = $this->module;
      $this->module = $module;
      return $prevModule;
   }

   /**
    * Associates a name with an object. The association will be invalidated at
    * the end of the scope.
    */
   public function bind( string $name, Node $object ): Binding {
      $scope = $this->getActiveScope();
      if ( ! array_key_exists( $name, $scope->bindings ) ) {
         $binding = new Binding();
         $binding->name = $name;
         $binding->node = $object;
         $scope->bindings[ $name ] = $binding;
         return $binding;
      }
      else if ( $scope->bindings[ $name ]->node->nodeType ==
         NODE_SEEN ) {
         $scope->bindings[ $name ]->node = $object;
         return $scope->bindings[ $name ];
      }
      else {
         $this->user->diag( DIAG_ERR, $this->getObjectPos( $object ),
            'name `%s` already used', $name );
         $this->user->diag( DIAG_NOTICE, $this->getObjectPos(
            $scope->bindings[ $name ]->node ),
            'name `%s` previously used here', $name );
         $this->user->bail();
      }
   }

   public function replace( string $name ): ?Binding {
      $scope = $this->getActiveScope();
      if ( array_key_exists( $name, $scope->bindings ) ) {
         return $scope->bindings[ $name ];
      }
      else {
         return null;
      }
   }

   private function getActiveScope(): ScopeFloor {
      if ( count( $this->floors ) > 1 ) {
         return $this->activeFloor;
      }
      else {
         return $this->module->scope;
      }
   }

   private function getObjectPos( Node $object ): Lexing\Position {
      if ( $object instanceof Func ||
         $object instanceof Enumeration ||
         $object instanceof Enumerator ||
         $object instanceof Structure ||
         $object instanceof Let ||
         $object instanceof Param ||
         $object instanceof TypeParam ||
         $object instanceof ImportItem ||
         $object instanceof Variable ||
         $object instanceof Constant ||
         $object instanceof Generic ) {
         return $object->pos;
      }
      else {
         var_dump( $object->nodeType );
         var_dump( get_class( $object ) );
         UNREACHABLE();
      }
   }

   public function dumpLocalScope(): void {
      var_dump( $this->activeFloor );
   }

   public function createBinding( string $name ): Binding {
      $scope = $this->getActiveScope();
      return $scope->createBinding( $name );
   }

   public function enter( int $type = SCOPE_NORMAL ): void {
      $floor = new ScopeFloor( $type );
      array_unshift( $this->floors, $floor );
      $this->activeFloor = $floor;
   }

   public function leave(): void {
      if ( count( $this->floors ) > 1 ) {
         $this->closeActiveScope();
      }
   }


   private function closeActiveScope(): void {
      $oldScope = array_shift( $this->floors );
      $this->activeFloor = $this->floors[ 0 ];

      // All bindings must not refer to any allocated values.
      /*
      foreach ( $oldScope->bindings as $binding ) {
         if ( $binding->value !== null &&
            $this->describe( $binding->value->type ) !== Desc::VOID &&
            $this->describe( $binding->value->type ) !== Desc::NEVER &&
            $this->describe( $binding->value->type ) !== Desc::ERR &&
            $this->describe( $binding->value->type ) !== Desc::PLACEHOLDER &&
            $this->describe( $binding->value->type ) !== Desc::STRUCT_TYPE &&
            $this->isOwned( $binding->value->type ) ) {
            $this->user->diag( DIAG_ERR, $binding->node->pos,
               "value that is attached to `%s` must be dropped or moved",
               $binding->name );
            $this->user->bail();
         }
      }
      */

      #printf( "pop scope: %s\n", scopeName( $oldScope->type ) );
      // For certain scope types, we transfer the bindings found in these
      // scopes into the parent scope.
      switch ( $oldScope->type ) {
      case SCOPE_OR:
         switch ( $this->activeFloor->type ) {
         case SCOPE_OR:
         case SCOPE_RSIDE:
            $this->transferBindings( $oldScope );
            break;
         case SCOPE_AND:
         case SCOPE_CONDITION:
         case SCOPE_NORMAL:
            $this->transferBindings( $oldScope, true );
            break;
         default:
            throw new \Exception();
         }
         break;
      case SCOPE_RSIDE:
         // All bindings that appear on the right side of a logical operator
         // depend on the execution of the left side and are, therefore, made
         // conditional.
         foreach ( $oldScope->bindings as $binding ) {
            $binding->conditional = true;
         }
         $this->transferBindings( $oldScope );
         break;
      case SCOPE_AND:
         switch ( $this->activeFloor->type ) {
         case SCOPE_OR:
         case SCOPE_NORMAL:
            $this->transferBindings( $oldScope, true );
            break;
         case SCOPE_AND:
         case SCOPE_RSIDE:
         case SCOPE_CONDITION:
            $this->transferBindings( $oldScope );
            break;
         default:
            throw new \Exception();
         }
         break;
      case SCOPE_CONDITION:
         $this->transferBindings( $oldScope, true );
         break;
      case SCOPE_NORMAL:
         break;
      default:
         throw new \Exception();
      }
   }

   private function transferBindings( ScopeFloor $floor,
      bool $onlyUnconditional = false ): void {
      #printf( "moving bindings to parent scope: %s\n", scopeName(
      #   $this->activeScope->type ) );
      foreach ( $floor->bindings as $binding ) {
         if ( ! array_key_exists( $binding->name,
            $this->activeFloor->bindings ) ) {
            if ( ( $onlyUnconditional && $binding->conditional == false ) ||
               $onlyUnconditional == false ) {
               $this->activeFloor->bindings[ $binding->name ] = $binding;
               #printf( "transferring %s (conditional: %s)\n", $binding->name,
               #   $binding->conditional ? "true" : "false" );
            }
         }
         else {
            printf( "error: name `%s` already used\n", $binding->name );
            throw new Exception();
         }
      }
   }

   public function getInCurrentScope( string $name ): Binding {
      $floor = $this->getActiveScope();
      if ( array_key_exists( $name, $floor->bindings ) ) {
         return $floor->bindings[ $name ];
      }
      else {
         $binding = new Binding();
         $binding->name = $name;
         $floor->bindings[ $name ] = $binding;
         return $binding;
      }
   }

   public function get( string $name, Lexing\Position $pos = null,
      Module $module = null ): ?Binding {
      $finder = new ItemFinder( $this->user, $this->floors,
         $this->module );
      $result = $finder->search( $name, $pos, $module );
      $binding = $result?->binding;
      if ( $binding !== null ) {
         if ( $binding->value === null ) {
            $binding->value = $this->createValueForNode( $binding,
               $result->moduleFoundIn );
            $binding->module = $result->moduleFoundIn;
         }
         else {

            // Duplicate the value for each new scope, so refinements can be
            // easily discarded at the end of each scope.
            $closestBinding = $this->getInCurrentScope( $binding->name );
            if ( $closestBinding->value === null ) {
               $value = clone $binding->value;
               $closestBinding->value = $value;
               $closestBinding->node = $binding->node;
               $closestBinding->rebindable = $binding->rebindable;
               $binding = $closestBinding;
            }
         }
      }
      return $binding;
   }

   private function createValueForNode( Binding $binding,
      Module $module ): Value {
      $node = $binding->node;
      if ( $node instanceof \ImportItem ) {
         $node = $node->object;
      }

      if ( $node instanceof \Let ) {
         return $this->createBindingUsageValue( $binding );
      }
      else if ( $node instanceof \Constant ) {
         return $this->createValueForConstant( $node );
      }
      else if ( $node instanceof \Enumerator ) {
         return $this->createValueForEnumerator( $node );
      }
      else if ( $node instanceof \Param ) {
         return $this->createValueForParam( $node, $binding );
      }
      else if ( $node instanceof \Func ) {
         return $this->createValueForFunc( $node, $module );
      }
      else if ( $node instanceof \Structure ) {
         return $this->createValueForStruct( $node );
      }
      else if ( $node instanceof \Enumeration ) {
         return $this->createValueForEnum( $node );
      }
      else if ( $node instanceof \TypeAlias ) {
         $result = new Value();
         $result->type = $node->type;
         //$result->type = $let->type->createInstance();
         //$result->type = $b->type;
         $result->evaluable = true;
         $result->constant = true;
         return $result;
      }
      else if ( $node instanceof \Generic ) {
         $result = new Value();
         $result->type->params = $node->params;
         $result->type->spec = TYPESPEC_GENERIC;
         $result->generic = $node;
         //$result->type = $let->type->createInstance();
         //$result->type = $b->type;
         $result->evaluable = true;
         $result->constant = true;
         return $result;
      }
      else if ( $node instanceof \TypeParam ) {
         $value = new Value();
         $value->type = $node->type;
         return $value;
      }
      else {
         UNREACHABLE( 'unhandled node type: %s',
            get_class( $binding->node ) );
      }
   }

   private function createBindingUsageValue( \Binding $binding ): Value {
      //return $binding->value;
      $result = clone $binding->value;
      //$result->type = $let->type->createInstance();
      //$result->type = $b->type;
      $result->evaluable = true;
      $result->constant = true;
      $result->mutableBinding = $binding->rebindable;
      $result->binding = $binding;
      return $result;
   }

   private function createValueForConstant( \Constant $constant ): Value {
      if ( ! $constant->resolved ) {
         $value = new Value();
         $value->type->spec = TYPESPEC_UNRESOLVED;
         return $value;
      }
      return $constant->value2;
      $result = new Value();
      $result->inhabitant = $constant->value->value;
      $result->constant = true;
      $result->type = $constant->type;
      //$result->type->value = $constant;
      //$result->type->spec = TYPESPEC_VALUE;
      $result->diag = $constant->diag;
      return $result;
   }

   private function createValueForEnumerator(
      \Enumerator $enumerator ): Value {
      if ( ! $enumerator->enumeration->resolved ) {
         $value = new Value();
         $value->type->spec = TYPESPEC_UNRESOLVED;
         return $value;
      }
      else {
         $result = new Value();
         $result->inhabitant = $enumerator->value;
         $result->type->spec = TYPESPEC_ENUM;
         $result->type->enumeration = $enumerator->enumeration;
         $result->enumerator = $enumerator;
         $result->evaluable = true;
         $result->constant = true;
         return $result;
      }
   }

   private function createValueForParam( \Param $param,
      \Binding $binding ): Value {
      if ( $binding->value === null ) {
         /*
            $result = new Result();
            $result->type = $param->type;
            $result->constant = $param->constant;
            $result->evaluable = true;
            $result->binding = $binding;
            $binding->value = $result;
                  // Dereference.
            if ( $usage->args !== null ) {
               $result = $this->deref( $usage->pos, $result, $usage->args );
            }
         */
         $binding->value = $param->value;
      }
      //return $binding->value;
      $result = clone $binding->value;

      return $result;
   }

   private function createValueForFunc( \Func $func, \Module $owner ): Value {
      if ( ! $func->resolved ) {
         $value = new Value();
         $value->type->spec = TYPESPEC_UNRESOLVED;
         return $value;
      }
      else {
         $result = new Value();
         $result->func = $func;
         $result->owner = $owner;
         $result->evaluable = $func->evaluable;
         $result->constant = true;
         return $result;
      }
   }


   private function createValueForStruct( \Structure $structure ): Value {
      if ( ! $structure->resolved ) {
         $value = new Value();
         $value->type->spec = TYPESPEC_UNRESOLVED;
         return $value;
      }
      else {
         $result = new Value();
         $result->type->structure = $structure;
         $result->type->spec = TYPESPEC_STRUCT_TYPE;
         $result->constant = true;
         $result->evaluable = true;
         $result->inhabitant = $structure;
         return $result;
      }
   }

   private function createValueForEnum( \Enumeration $enumeration ): Value {
      $result = new Value();
      $result->type->enumeration = $enumeration;
      $result->type->spec = TYPESPEC_ENUM_TYPE;
      //$result->type->mutable = true;
      //$result->type->mutable = $usage->mutable;
      $result->constant = true;
      $result->evaluable = true;
      return $result;
   }
}

