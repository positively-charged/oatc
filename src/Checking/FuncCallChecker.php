<?php

declare( strict_types = 1 );

namespace Checking;

use Arg;
use Call;
use Expr;
use Lexing\Position;
use Param;
use Typing\Describer;
use Typing\DescriberUsage;
use Typing\Description as Desc;
use Typing\InstanceChecker;
use Typing\InstanceCheckerUsage;
use Typing\Presenter;
use Typing\PresenterUsage;
use Typing\Type;
use Typing\TypeChecker;
use User;

class FuncCallChecker {
   use DescriberUsage;
   use PresenterUsage;
   use InstanceCheckerUsage;

   private int $count;
   /** @var Value[] */
   private array $args;
   /** @var string[] */
   private array $names;
   private bool $nonConstantArgs;
   private ?Value $returnValue;

   public function __construct(
      private Describer $typeDescriber,
      private Presenter $typePresenter,
      private InstanceChecker $instanceChecker,
      private SimpleExprChecker $simpleExprChecker,
      private TypeChecker $typeChecker,
      private User $user,
      private Call $call,
      private Value $operand ) {
      $this->count = 0;
      $this->args = [];
      $this->names = [];
      $this->nonConstantArgs = false;
      $this->returnValue = null;
   }

   public function check(): Value {
      if ( $this->returnValue === null ) {
         $this->checkFuncCall();
      }
      return $this->returnValue;
   }

   private function checkFuncCall(): void {
      if ( $this->operand->func !== null ) {
         $this->checkArgs();
         $this->checkRemainingArgs();
         $this->finish();
      }
      else {
         $this->err( $this->call->pos,
            'attempting to call something that is not a function' );
      }
   }

   private function checkArgs(): void {
      $this->args = [];
      if ( $this->operand->method ) {
         /*
         if ( ! ( count( $this->operand->func->params ) > 0 ) ) {
            $this->err( $this->call->pos, 'too many arguments' );
         }
         $this->checkArg2( $this->operand->func->params[ 0 ], $this->operand );
         */
         //$this->args[] = $this->operand;
         //++$this->count;
      }

      foreach ( $this->call->args as $arg ) {
         $result = $this->simpleExprChecker->checkExpr( $arg->expr );
         $this->args[] = $result;
      }

      $paramPos = 0;

      if ( $this->operand->method ) {
         if ( ! ( count( $this->operand->func->params ) > 0 ) ) {
            $this->err( $this->call->pos, 'too many arguments' );
         }

         $operand = clone $this->operand;
         $operand->borrowed = true;

         // Move argument to function.
         if ( $operand->binding !== null &&
            ! $this->operand->func->params[ 0 ]->type->borrowed ) {
            $operand->binding->value = new Value();
         }

         //$this->checkArg2( $this->operand->func->params[ 0 ], $operand );
         //++$this->count;
         ++$paramPos;
      }

      // Check arguments for positional parameters. Arguments for positional
      // parameters can be named and must occur in the order of the positional
      // parameters.

      while ( true ) {
         if ( ! ( $paramPos < count( $this->operand->func->params ) &&
            $this->operand->func->params[ $paramPos ]->defaultArg === null ) ) {
            break;
         }

         $param = $this->operand->func->params[ $paramPos ];

         if ( $this->count < count( $this->args ) ) {
            $this->checkArg( $param,
               $this->call->args[ $this->count ], $this->args[ $this->count ] );
         }
         else {
            if ( $param->defaultArg === null ) {
               $this->err( $this->call->pos, 'too little arguments' );
            }
         }

         ++$this->count;
         ++$paramPos;
      }

      // Check arguments for optional parameters. Arguments for optional
      // parameters can appear in any order.

      while ( true ) {
         if ( ! ( $paramPos < count( $this->operand->func->params ) &&
            $this->operand->func->params[ $paramPos ]->defaultArg !== null ) ) {
            break;
         }

         if ( $this->count < count( $this->call->args ) ) {
            $arg = $this->call->args[ $this->count ];

            // Positional
            if ( $arg->name === '' ) {
               // Positional arguments are not allowed after named arguments.
               if ( count( $this->names ) > 0 ) {
                  $this->err( $arg->expr->pos,
                     'positional argument after a named argument' );
               }
               $param = $this->operand->func->params[ $paramPos ];
            }
            else {
               $param = null;
               foreach ( $this->operand->func->params as $p ) {
                  if ( $p->name === $arg->name ) {
                     $param = $p;
                     break;
                  }
               }
               if ( $param === null ) {
                  $this->err( $arg->expr->pos, 'parameter `%s` not found',
                     $arg->name );
               }
            }


            $this->checkArg( $param, $arg, $this->args[ $this->count ] );
            ++$this->count;
         }
         else {
            break;
         }

      }

/*
      foreach ( $this->operand->func->params as $param ) {
         if ( $this->count < count( $this->args ) ) {
            $this->checkArg( $param, $this->args[ $this->count ] );
         }
         else {
            if ( ! $param->defaultArg ) {
               $this->err( $this->call->pos, 'too little arguments' );
            }
         }
         ++$this->count;
      }
*/
   }

   private function getNextPositionalParam(): ?Param {
      if ( $this->count < count( $this->operand->func->params ) &&
         $this->operand->func->params[ $this->count ]->defaultArg === null ) {
         $param = $this->operand->func->params[ $this->count ];
         ++$this->count;
         return $param;
      }
      return null;
   }

   private function checkArg( Param $param, Arg $passedArg,
      Value $arg ): void {

      //$arg = $this->simpleExprChecker->checkExpr( $passedArg->expr );
      //$this->args[] = $arg;

      if ( $passedArg->name !== '' ) {
         if ( in_array( $passedArg->name, $this->names ) ) {
            $this->err( $passedArg->expr->pos, 'argument label `%s` ' .
               'specified multiple times', $passedArg->name );
         }

         $this->names[] = $passedArg->name;

         if ( $passedArg->name !== $param->name ) {
            $this->err( $passedArg->expr->pos, 'expecting `%s` as label for ' .
               'parameter %d, but got `%s`', $param->name,
               $this->count + 1, $passedArg->name );
         }
      }

      $this->checkArg2( $param, $arg );
   }

   private function checkArg2( Param $param, Value $arg ): void {
      $paramType = new Type();
      $paramType->spec = $param->type->spec;
      $paramType->structure = $param->type->structure;
      $paramType->enumeration = $param->type->enumeration;
      $paramType->borrowed = $param->type->borrowed;
      $paramType->mutable = $param->type->mutable;
      $paramType->args = $this->operand->type->args;
      $paramType->placeholder = $param->type->placeholder;
      $paramType->typeParam = $param->type->typeParam;
      $paramType->value = $param->type->value;
      $paramType->unchecked = $param->type->unchecked;

      if ( $this->describe( $paramType ) === Desc::ERR ) {
         $result = new Value();
         $result->type->spec = TYPESPEC_ERR;
         $result->diag = $param->expectedType->diag;
         throw new CheckErr( $result );
      }

      if ( ! $this->isInstanceOf( $arg, $paramType ) ) {
         $this->err( $this->call->args[ $this->count ]->expr->pos,
            'argument %d of wrong type (`%s`), expecting `%s`',
            $this->count + 1,
            $this->presentType( $arg->type ),
            $this->presentType( $param->type ) );
      }

      // Virtual functions are executed at compile-time and must have
      // constant arguments.
      if ( $this->operand->func->virtual && ! $arg->evaluable ) {
         $this->user->diag( DIAG_ERR, $this->call->args[ $this->count ]->expr->pos,
            "argument for a virtual function must be evaluable" );
         $this->user->bail();
      }

      // Move argument to function.
      if ( $arg->binding !== null &&
         ! $arg->borrowed ) {
         $arg->binding->value = new Value();
      }

      if ( ! $arg->constant ) {
         $this->nonConstantArgs = true;
      }
   }

   private function checkRemainingArgs(): void {
      if ( $this->count < count( $this->call->args ) ) {
         $arg = $this->simpleExprChecker->checkExpr(
            $this->call->args[ $this->count ]->expr );
         $this->args[] = $arg;
         if ( $this->operand->func->variadic ) {
            /*
               $argType = $this->getVarArgType( $operand->func->argsParam->type );
               while ( $count < count( $args ) ) {
                  if ( ! $this->typeChecker->isInstanceOf( $args[ $count ]->type,
                     $argType ) ) {
                     printf( "error: argument %d of wrong type\n", $count + 1 );
                     exit( 1 );
                  }
                  ++$count;
               }*/
         }
         else {
            $this->err( $this->call->pos,
               "too many arguments" );
         }
      }
   }

   private function finish(): void {
      $result = new Value();

      if ( $this->operand->func->returnType !== null ) {
         $result->type = $this->operand->func->returnType;
         if ( $this->operand->func->returnTypeExpr !== null ) {
            $result->diag = $this->operand->func->returnTypeExpr->diag;
         }
      }

      // Resolve a function out of order.
      if ( ! $this->operand->func->resolved &&
         $this->simpleExprChecker->doesFuncNeedToBeResolved() ) {
         $this->simpleExprChecker->resolveFunc( $this->operand->owner,
            $this->operand->func );
      }

      $result->evaluable = $this->operand->func->evaluable;
      if ( ( $result->evaluable && ! $this->nonConstantArgs ) ||
         $result->type->constant ) {
         $result->constant = true;
      }

      if ( $this->operand->func->returnTypeExpr !== null ) {
         foreach ( $this->operand->func->returnTypeExpr->options as $option ) {
            if ( $this->isVoid( $option->type ) ) {
               foreach ( $option->refinements as $refinement ) {
                  $paramPos = $this->operand->func->getParamPos(
                     $refinement->target );
                  if ( $paramPos !== null ) {
                     $this->replaceRefinements( $this->args[ $paramPos ]
                        ->binding->value->type, $refinement->refinedType );
                  }
               }
            }
         }
      }

      ++$this->operand->func->numCalls;
      $this->call->func = $this->operand->func;
      /*
      if ( $call->func->virtual ) {
         $this->task->evaluator->callFunc( $call );
         $result->value = $call->result;
         $result->constant = true;
      } */
      $this->call->type = CALL_FUNC;
      if ( $this->operand->type->spec == TYPESPEC_TRAIT ) {
         $this->call->type = CALL_TRAIT;
      }
      $this->call->method = $this->operand->method;

      $this->returnValue = $result;
   }

   private function err( Position $pos, string $message, ... $args ): never {
      $result = new Value();
      $result->type->spec = TYPESPEC_ERR;
      $result->diag = $this->user->diag( DIAG_ERR, $pos, $message,
         ... $args );
      throw new CheckErr( $result );
   }
}
