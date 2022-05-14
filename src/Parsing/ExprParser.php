<?php

declare( strict_types = 1 );

namespace Parse;

class ExprParser extends Parser {
   private \Module $module;

   public function __construct( private \Task $task, \User $user,
      \Lexing\Lexer $lexer, \Lexing\ScopeLexer $scopeLexer, \Module $module ) {
      parent::__construct( $user, $lexer, $scopeLexer );
      $this->module = $module;
   }

   public function readTaggedExpr(): \Expr {
      $tag = '';
      if ( ( $this->scopeLexer->tkPotentialKw() === TK_ID &&
         $this->scopeLexer->peek() === TK_COLON ) ) {
         $tag = $this->scopeLexer->copyTokenText();
         $this->readTk();
         $this->testTk( TK_COLON );
         $this->readTk();
      }

      $expr = $this->readExpr();
      $expr->tag = $tag;

      return $expr;
   }

   public function readExpr(): \Expr {

      $expr = new \Expr();
      if ( $this->scopeLexer->tkPotentialKw() == TK_VIRT ) {
         $expr->virtual = true;
         $this->readTk();
      }
      $expr->pos = $this->scopeLexer->token->pos;
      /*
      if ( $this->lexer->tk == TK_ID ) {
         $binding = $this->scope->getBinding( $this->lexer->copyTokenText() );
         if ( $binding == null && $this->lexer->kw() ) {

         }
      }*/
      /*
      if ( $this->isPlainExprStartedById() ) {
         $this->readPlainExpr();
      }
      else {*/
         //$this->keywordBank->translateIdToKw();
         //switch ( $this->lexer->potentialKw() ) {
         switch ( $this->scopeLexer->tkPotentialKw() ) {
         case TK_LET:
            $expr->root = $this->readLet();
            break;
         case TK_REB:
            $expr->root = $this->readRebind();
            break;
         case TK_LBRACE:
         case TK_IF:
         case TK_SWITCH:
         case TK_MATCH:
         case TK_WHILE:
         case TK_FOR:
         case TK_BREAK:
         case TK_CONTINUE:
         case TK_RETURN:
         case TK_DROP:
            $expr->root = $this->readStmt();
            $expr->compound = true;
            break;
         default:
            $expr->root = $this->readAssignment();
         }
      //}
      return $expr;
   }

   private function readDeclExpr(): \Node {
      $declParser = new DeclParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_STRUCT:
         return $declParser->readStruct( [], false );
      case TK_LBRAC:
         return $declParser->readAnonStruct( [], false );
      case TK_ENUM:
      case TK_VARIAN:
         return $declParser->readEnum( false );
      case TK_FUN:
         return $declParser->readFunc();
      }
   }

   public function readConstantExpr(): \Expr {
      $expr = new \Expr();
      $expr->pos = $this->scopeLexer->token->pos;
      $expr->root = $this->readLogical();
      return $expr;
   }

   public function readBlockStmt(): \BlockStmt {
      $this->scopeLexer->pushScope();

      $stmt = new \BlockStmt();
      $stmt->pos = $this->scopeLexer->token->pos;
      // Single-line block statement.
      if ( $this->scopeLexer->tk == TK_COLON ) {
         $this->readTk();
         array_push( $stmt->stmts, $this->readBlockItem() );
      }
      // Multi-line block statement.
      else {
         //$this->testTk( TK_DO );
         //$this->readTk();
         //$this->testTk( TK_NL );
         //$this->readTk();
         $this->testTk( TK_LBRACE );
         $this->readTk();
         if ( $this->scopeLexer->tk != TK_RBRACE ) {
            $stmt->stmts = $this->readStmtList();
         }
         $this->testTk( TK_RBRACE );
         $this->readTk();
         //if ( $this->lexer->tk == TK_NL ) {
         //   $this->readTk();
         //}
         /*
         if ( $this->lexer->tk == TK_ENDKW ) {
            $this->readTk();
         }
         */
      }

      $this->scopeLexer->popScope();

      return $stmt;
   }

   private function readStmtList(): array {
      $stmts = [];
      while ( true ) {
         $stmt = $this->readBlockItem();
         array_push( $stmts, $stmt );
         if ( $this->scopeLexer->tk == TK_RBRACE ) {
            break;
         }
      }
      return $stmts;
   }

   private function readBlockItem(): \Node {
      return $this->readExprStmt();
   }

   private function readStmt(): \Node {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_LBRACE:
         return $this->readBlockStmt();
      case TK_IF:
         return $this->readIfStmt();
      case TK_SWITCH:
         return $this->readSwitchStmt();
      case TK_MATCH:
         return $this->readMatch();
      case TK_WHILE:
         return $this->readWhileStmt();
      case TK_FOR:
         return $this->readForLoop();
      case TK_BREAK:
      case TK_CONTINUE:
         return $this->readJump();
      case TK_RETURN:
         return $this->readReturnStmt();
      case TK_DROP:
         return $this->readDrop();
      default:
         throw new \Exception();
      }
   }

   private function readIfStmt(): \IfStmt {
      $this->testKw( TK_IF );
      $stmt = new \IfStmt();
      do {
         $item = new \IfItem();
         $item->pos = $this->scopeLexer->token->pos;
         $this->readTk();
         $item->cond = $this->readExpr();
         $item->body = $this->readBlockStmt();
         array_push( $stmt->ifs, $item );
      } while ( $this->scopeLexer->tkPotentialKw() === TK_ELIF );
      if ( $this->scopeLexer->tkPotentialKw() === TK_ELSE ) {
         $this->readElse( $stmt );
      }
      return $stmt;
   }

   private function readElse( \IfStmt $stmt ): void {
      $this->testKw( TK_ELSE );
      $this->readTk();
      $stmt->elseBody = $this->readBlockStmt();
   }

   private function readSwitchStmt(): \SwitchStmt {
      $this->testKw( TK_SWITCH );
      $stmt = new \SwitchStmt();
      $this->readTk();
      $stmt->cond = $this->readExpr();
      $this->readCaseList( $stmt );
      return $stmt;
   }

   private function readCaseList( \SwitchStmt $stmt ): void {
      do {
         $case = $this->readCase( $stmt );
         array_push( $stmt->cases, $case );
      } while ( $this->scopeLexer->tkPotentialKw() == TK_CASE ||
         $this->scopeLexer->tkPotentialKw() == TK_DEFAULT );
   }

   private function readCase(): \SwitchCase {
      $case = new \SwitchCase();
      do {
         switch ( $this->scopeLexer->tkPotentialKw() ) {
         case TK_CASE:
            $this->readTk();
            array_push( $case->values, $this->readExpr() );
            break;
         case TK_DEFAULT:
            $case->isDefault = true;
            $this->readTk();
            break;
         default:
            throw new \Exception();
         }
         if ( $this->scopeLexer->tk == TK_COMMA ) {
            $this->readTk();
         }
      } while ( $this->scopeLexer->tkPotentialKw() == TK_CASE ||
         $this->scopeLexer->tkPotentialKw() == TK_DEFAULT );
      $case->body = $this->readBlockStmt();
      return $case;
   }

   private function readMatch(): \MatchExpr {
      $this->testKw( TK_MATCH );
      $this->readTk();
      $stmt = new \MatchExpr();
      $stmt->cond = $this->readExpr();
      $this->testTk( TK_LBRACE );
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RBRACE ) {
         $stmt->arms = $this->readMatchArmList();
         if ( $this->scopeLexer->tkPotentialKw() === TK_DEFAULT ) {
            $this->readTk();
            $arm = new \MatchArm();
            $arm->body = $this->readBlockStmt();
            $stmt->arms[] = $arm;
         }
      }
      $this->testTk( TK_RBRACE );
      $this->readTk();
      return $stmt;
   }

   private function readMatchArmList(): array {
      $arms = [];
      while ( $this->scopeLexer->tk !== TK_RBRACE &&
         $this->scopeLexer->tkPotentialKw() !== TK_DEFAULT ) {
         $arms[] = $this->readMatchArm();
      }
      return $arms;
   }

   private function readMatchArm(): \MatchArm {
      $arm = new \MatchArm();
      $arm->patterns = $this->readPatternList( TK_RBRACE );
      $arm->body = $this->readBlockStmt();
      return $arm;
   }

   private function readWhileStmt(): \WhileStmt {
      $this->testKw( TK_WHILE );
      $stmt = new \WhileStmt();
      $this->readTk();
      $stmt->cond = $this->readExpr();
      $stmt->body = $this->readBlockStmt();
      $stmt->endfully = $this->readEndfully();
      return $stmt;
   }

   private function readEndfully(): ?\BlockStmt {
      if ( $this->scopeLexer->tkPotentialKw() == TK_ENDFULLY ) {
         $this->readTk();
         return $this->readBlockStmt();
      }
      return null;
   }

   private function readForLoop(): \ForLoop {
      $this->testKw( TK_FOR );
      $loop = new \ForLoop();
      $this->readTk();
      if ( $this->scopeLexer->tk == TK_ID && $this->scopeLexer->peek() == TK_IN ) {
         $this->testTk( TK_ID );
         $loop->item = new \ForItem();
         $loop->item->name = $this->scopeLexer->copyTokenText();
         $this->readTk();
         $this->testKw( TK_IN );
         $this->readTk();
      }
      $loop->collection = $this->readExpr();
      $loop->body = $this->readBlockStmt();
      $loop->endfully = $this->readEndfully();
      return $loop;
   }

   private function readJump(): \Jump {
      $type = JUMP_BREAK;
      if ( $this->scopeLexer->tkPotentialKw() == TK_CONTINUE ) {
         $type = JUMP_CONTINUE;
      }
      else {
         $this->testKw( TK_BREAK );
      }
      $jump = new \Jump();
      $jump->pos = $this->scopeLexer->token->pos;
      $jump->type = $type;
      $this->readTk();
      return $jump;
   }

   private function readReturnStmt(): \ReturnStmt {
      $this->testKw( TK_RETURN );
      $stmt = new \ReturnStmt();
      $stmt->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_SEMICOLON ) {
         $stmt->value = $this->readExpr();
      }
      return $stmt;
   }

   /**
    * @return \Expr[]
    */
   private function readArgList(): array {
      return $this->readArgumentList( TK_RPAREN );
   }

   private function readDrop(): \DropExpr {
      $this->testKw( TK_DROP );
      $drop = new \DropExpr();
      $drop->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      $drop->values = $this->readArgList();
      return $drop;
   }

   private function readExprStmt(): \ExprStmt {
      $exprWithBlock = false;
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_LBRACE:
      case TK_IF:
      case TK_SWITCH:
      case TK_WHILE:
         // case TK_FOR:
         $exprWithBlock = true;
         break;
      }

      $stmt = new \ExprStmt();
      $stmt->expr = $this->readTaggedExpr();

      if ( $this->scopeLexer->tkPotentialKw() === TK_ON ) {
         $this->readTk();
         $this->testTk( TK_LBRACE );
         $this->readTk();
         $stmt->arms = $this->readMatchArmList();
         $this->testTk( TK_RBRACE );
         $this->readTk();
      }

      if ( $this->scopeLexer->tk !== TK_SEMICOLON ) {
         $stmt->yield = true;
      }
      else {
         $this->testTk( TK_SEMICOLON );
         $this->readTk();
      }
      //var_dump( $this->lexer->copyTokenText() );
      /*
      if ( ! $exprWithBlock ) {
         if ( $this->lexer->tk == TK_SEMICOLON ) {
            $stmt->terminated = true;
            $this->readTk();
            if ( $this->lexer->tk == TK_NL ) {
               $this->readTk();
            }
         }
         else {
           // $this->readTerminator();
         }
      }*/
      /*
      if ( $this->isTerminator() ) {
         $this->readTk();
         if ( $this->lexer->tk == TK_NL ) {
            $this->readTk();
         }
      }
      else {
         $this->testTk( TK_NL );
         $this->readTk();
         //$this->readTerminator();
         //$stmt->terminated = true;
      }*/
      return $stmt;
   }

   public function readLet(): \Let {
      $this->testKw( TK_LET );
      $binding = new \Let();
      $binding->pos = $this->scopeLexer->token->pos;
      $this->readTk();

      $paren = false;
      if ( $this->scopeLexer->tk === TK_LPAREN ) {
         $paren = true;
         $this->readTk();
      }

      $declParser = new DeclParser( $this->task, $this->user,
         $this->lexer, $this->scopeLexer, $this->module );
      $binding->unpackedTuple = $declParser->readUnpackedTuple();

      if ( $paren ) {
         $this->testTk( TK_RPAREN );
         $this->readTk();
         if ( $this->scopeLexer->tk === TK_EQ ) {
            $this->readTk();
            $binding->value = $this->readExpr();
         }
      }

/*
      if ( $this->scopeLexer->tk === TK_LPAREN ) {
         $declParser = new DeclParser( $this->task, $this->user,
            $this->lexer, $this->scopeLexer, $this->module );
         $binding->unpackedTuple = $declParser->readUnpackedTuple();
      }
      else {
         if ( $this->scopeLexer->tkPotentialKw() === TK_MUT ) {
            $binding->rebindable = true;
            $this->readTk();
         }

         $name = $this->readName();
         $binding->name = $name->value;
         $this->scopeLexer->bindName( $binding->name );
         $binding->name2 = $name;

         if ( $this->scopeLexer->tk === TK_COLON ) {
            $this->readTk();
            $declParser = new DeclParser( $this->task, $this->user,
               $this->lexer, $this->scopeLexer, $this->module );
            $binding->expectedType = $declParser->readTypeRequest();
         }
      }
*/

      return $binding;
   }

   private function readRebind(): \Node {
      $this->testKw( TK_REB );
      $assignment = new \Assignment();
      $assignment->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      $assignment->lside = $this->readLogical();
      $this->testTk( TK_EQ );
      $this->readTk();
      $assignment->rside = $this->readLogical();
      return $assignment;
   }

   private function readAssignment(): \Node {
      $lside = $this->readLogical();
      switch ( $this->scopeLexer->tk ) {
      case TK_EQ:
         $assignment = new \Assignment();
         $assignment->pos = $this->scopeLexer->token->pos;
         $this->readTk();
         $assignment->lside = $lside;
         $assignment->rside = $this->readAssignment();
         $lside = $assignment;
         break;
      }
      return $lside;
   }

   private function readLogical(): \Node {
      $lside = $this->readLogicalNot();
      while ( true ) {
         //switch ( $this->getKw() ) {
         switch ( $this->scopeLexer->tkPotentialKw() ) {
         case TK_AND:
            $op = \Logical::OPERATOR_AND;
            break;
         case TK_OR:
            $op = \Logical::OPERATOR_OR;
            break;
         default:
            return $lside;
         }
         $logical = new \Logical();
         $logical->pos = $this->scopeLexer->token->pos;
         $logical->operator = $op;
         $logical->lside = $lside;
         $this->readTk();
         $logical->rside = $this->readLogicalNot();
         $lside = $logical;
      }
   }

   private function readLogicalNot(): \Node {
      if ( $this->scopeLexer->tkPotentialKw() == TK_NOT ) {
         $this->readTk();
         $logical = new \LogicalNot();
         $logical->pos = $this->scopeLexer->token->pos;
         $logical->operand = $this->readLogicalNot();
         return $logical;
      }
      else {
         return $this->readComparison();
      }
   }

   private function readComparison(): \Node {
      $lside = $this->readAddition();
      while ( true ) {
         $operator = null;
         switch ( $this->scopeLexer->tk ) {
         case TK_EQEQ:
            $operator = \Binary::OP_EQ;
            break;
         case TK_BANG_EQ:
            $operator = \Binary::OP_NEQ;
            break;
         case TK_LT:
            $operator = \Binary::OP_LT;
            break;
         case TK_LTEQ:
            $operator = \Binary::OP_LTE;
            break;
         case TK_GT:
            $operator = \Binary::OP_GT;
            break;
         case TK_GTEQ:
            $operator = \Binary::OP_GTE;
            break;
         default:
            return $lside;
         }
         $binary = new \Binary();
         $binary->pos = $this->scopeLexer->token->pos;
         $binary->op = $operator;
         $binary->lside = $lside;
         $this->readTk();
         $binary->rside = $this->readAddition();
         $lside = $binary;
      }
   }

   private function readAddition(): \Node {
      $lside = $this->readMultiplication();
      while ( true ) {
         $operator = null;
         switch ( $this->scopeLexer->tk ) {
         case TK_PLUS:
            $operator = \Binary::OP_ADD;
            break;
         case TK_MINUS:
            $operator = \Binary::OP_SUB;
            break;
         default:
            return $lside;
         }
         $binary = new \Binary();
         $binary->pos = $this->scopeLexer->token->pos;
         $binary->op = $operator;
         $binary->lside = $lside;
         $this->readTk();
         $binary->rside = $this->readMultiplication();
         $lside = $binary;
      }
   }

   private function readMultiplication(): \Node {
      $lside = $this->readPrefix();
      while ( true ) {
         switch ( $this->scopeLexer->tkPotentialKw() ) {
         case TK_STAR:
            $operator = \Binary::OP_MUL;
            break;
         case TK_SLASH:
            $operator = \Binary::OP_DIV;
            break;
         case TK_MOD:
            $operator = \Binary::OP_MOD;
            break;
         default:
            return $lside;
         }
         $binary = new \Binary();
         $binary->pos = $this->scopeLexer->token->pos;
         $binary->op = $operator;
         $binary->lside = $lside;
         $this->readTk();
         $binary->rside = $this->readPrefix();
         $lside = $binary;
      }
   }

   private function readPrefix(): \Node {
      switch ( $this->scopeLexer->tk ) {
      case TK_MINUS:
         $op = UOP_MINUS;
         break;
      case TK_PLUS:
         $op = UOP_PLUS;
         break;
      case TK_PLUS_PLUS:
         $op = UOP_PRE_INC;
         break;
      case TK_MINUS_MINUS:
         $op = UOP_PRE_DEC;
         break;
      case TK_BITAND:
         $op = UOP_ADDR_OF;
         break;
      default:
         return $this->readLike();
      }
      $unary = new \Unary();
      $unary->pos = $this->scopeLexer->token->pos;
      $unary->op = $op;
      $this->readTk();
      $unary->operand = $this->readPrefix();
      return $unary;
   }

   private function readLike(): \Node {
      $operand = $this->readSuffix();
      if ( $this->scopeLexer->tk === TK_TIDLE_TILDE ||
         $this->scopeLexer->tk === TK_BANG_TIDLE ) {
         $not = ( $this->scopeLexer->tk === TK_BANG_TIDLE );
         $this->readTk();
         $like = new \Like();
         $like->operand = $operand;
         $like->pattern = $this->readPattern();
         $like->not = $not;
         $operand = $like;
      }
      return $operand;
   }

   private function readPattern(): \Pattern {
      $pattern = new \Pattern();
      $pattern->pos = $this->scopeLexer->token->pos;
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_ID:
         $this->readPatternStartedById( $pattern );
         break;
      case TK_INTEGER_LITERAL:
         $this->readIntegerPattern( $pattern );
         break;
      case TK_TRUE:
      case TK_FALSE:
         $this->readBoolPattern( $pattern );
         break;
      default:
         $this->user->diag( DIAG_ERR, $pattern->pos,
            'unexpected token: %s', $this->scopeLexer->copyTokenText() );
         $this->user->bail();
      }
      return $pattern;
   }

   private function readPatternStartedById( \Pattern $pattern ): void {
      $this->testTk( TK_ID );
      $pattern->name = $this->scopeLexer->copyTokenText();
      $pattern->type = PATTERN_NAME;
      $this->readTk();
      if ( $this->scopeLexer->tk == TK_LPAREN ) {
         $this->readTk();
         #$pattern->type = PATTERN_CALL;
         if ( $this->scopeLexer->tk !== TK_RPAREN ) {
            //$pattern->namedArgs = $this->readTuple();
            //$pattern->args = $this->readPatternList( TK_RPAREN );
            $pattern->args = $this->readArgPatternList();
         }
         $this->testTk( TK_RPAREN );
         $this->readTk();
      }
   }

   private function readArgPatternList(): array {
      $args = [];
      while ( $this->scopeLexer->tk === TK_ID ) {
         $param = '';
         if ( $this->scopeLexer->peek() === TK_COLON ) {
            $param = $this->scopeLexer->copyTokenText();
            $this->readTk();
            $this->testTk( TK_COLON );
            $this->readTk();
         }
         $arg = $this->readPattern();
         $arg->param = $param;
         $args[] = $arg;
         if ( $this->scopeLexer->tk == TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk == TK_RPAREN ) {
               break;
            }
         }
         else if ( $this->scopeLexer->tk == TK_RPAREN ) {
            break;
         }
         else {
            break;
         }
      }
      return $args;
   }

   private function readPatternList( int $endTk ): array {
      $patterns = [];
      while ( true ) {
         $pattern = $this->readPattern();
         $patterns[] = $pattern;
         if ( $this->scopeLexer->tk == TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk == $endTk ) {
               break;
            }
         }
         else {
            break;
         }
      }
      return $patterns;
   }

   private function readIntegerPattern( \Pattern $pattern ): void {
      $pattern->integerLiteral = $this->readIntegerLiteral();
      $pattern->type = PATTERN_INTEGER_LITERAL;
   }

   private function readBoolPattern( \Pattern $pattern ): void {
      $pattern->boolLiteral = $this->readBoolLiteral();
      $pattern->type = PATTERN_BOOL_LITERAL;
   }

   private function readSuffix(): \Node {
      $operand = $this->readPrimary();
      while ( true ) {
         switch ( $this->scopeLexer->tk ) {
         case TK_LPAREN:
            $operand = $this->readCall( $operand );
            break;
         case TK_DOT:
            $operand = $this->readAccess( $operand );
            break;
         case TK_LBRAC:
            $operand = $this->readSubscript( $operand );
            break;
         case TK_QUESTION_MARK:
            $operand = $this->readPropagation( $operand );
            break;
         default:
            return $operand;
         }
      }
   }

   private function readCall( \Node $operand ): \Call {
      $this->testTk( TK_LPAREN );
      $call = new \Call();
      $call->pos = $this->scopeLexer->token->pos;
      $call->operand = $operand;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $call->args = $this->readArgumentList( TK_RPAREN );
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $call;
   }

   /**
    * @return \Arg[]
    */
   public function readArgumentList( int $endTk ): array {
   /*
      if ( $this->scopeLexer->peek() === TK_COLON ) {
         $args = $this->readTuple();
         return $args->args;
      }
   */
      if ( $this->scopeLexer->tk === TK_COMMA ) {
         $this->readTk();
      }

      $args = [];
      while ( true ) {
         $args[] = $this->readArg();
         if ( $this->scopeLexer->tk === TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk === $endTk ) {
               break;
            }
         }
         else if ( $this->scopeLexer->tk === $endTk ) {
            break;
         }
         else {
            break;
         }
      }
      return $args;
   }

   public function readArg(): \Arg {
      $arg = new \Arg();

/*
      if ( ( $this->scopeLexer->tk === TK_ID &&
         ( $this->scopeLexer->peek() === TK_EQ ||
            $this->scopeLexer->peek() === TK_COLON ) ) ) {
         $arg->name = $this->scopeLexer->copyTokenText();
         $this->readTk();
         if ( $this->scopeLexer->tk === TK_COLON ) {
            $this->readTk();
            $declParser = new DeclParser( $this->task, $this->user,
               $this->lexer, $this->scopeLexer, $this->module );
            $arg->typeRequest = $declParser->readTypeRequest();
         }
         $this->testTk( TK_EQ );
         $this->readTk();
      }
*/

      if ( ( $this->scopeLexer->tk === TK_ID ||
         $this->scopeLexer->tk === TK_UNDERSCORE ) &&
         $this->scopeLexer->peek() === TK_COLON ) {
         if ( $this->scopeLexer->tk === TK_ID ) {
            $arg->name = $this->scopeLexer->copyTokenText();
         }
         $this->readTk();
         $this->testTk( TK_COLON );
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_EQ ) {
            $declParser = new TypeExprParser( $this->task, $this->user,
               $this->lexer, $this->scopeLexer, $this->module );
            $arg->typeExpr = $declParser->readTypeExpr();
         }
         $this->testTk( TK_EQ );
         $this->readTk();
      }

      $arg->expr = $this->readExpr();

      return $arg;
   }

   private function readAccess( \Node $operand ): \Access {
      $this->testTk( TK_DOT );
      $access = new \Access();
      $access->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      $access->lside = $operand;
      $this->testTk( TK_ID );
      $access->memberName = $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $access;
   }

   private function readSubscript( \Node $operand ): \Subscript {
      $this->testTk( TK_LBRAC );
      $subscript = new \Subscript();
      $subscript->pos = $this->scopeLexer->token->pos;
      $subscript->operand = $operand;
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RBRAC ) {
         $subscript->indexes = $this->readArgumentList( TK_RBRAC );
      }
      $this->testTk( TK_RBRAC );
      $this->readTk();
      if ( $this->scopeLexer->tk === TK_EQ ) {
         $this->readTk();
         $subscript->value = $this->readLogical();
      }
      return $subscript;
   }

   private function readPropagation( \Node $operand ): \Propagation {
      $this->testTk( TK_QUESTION_MARK );
      $propagation = new \Propagation();
      $propagation->pos = $this->scopeLexer->token->pos;
      $propagation->operand = $operand;
      $this->readTk();
      return $propagation;
   }

   private function readPrimary(): \Node {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_NULL:
         return $this->readNull();
      case TK_SIZEOF:
         return $this->readSizeof();
      case TK_MODKW:
         return $this->readModPrefix();
      case TK_ID:
      case TK_MUT:
         return $this->readNameUsage();
      case TK_INTEGER_LITERAL:
         return $this->readIntegerLiteral();
      case TK_STRING_LITERAL:
         return $this->readStringLiteral();
      case TK_TRUE:
      case TK_FALSE:
         return $this->readBoolLiteral();
      case TK_STRUCT:
      case TK_ENUM:
      case TK_VARIAN:
      case TK_FUN:
      case TK_LBRAC:
         return $this->readDeclExpr();
      case TK_COLON:
         return $this->readTypeLiteral();
      case TK_LPAREN:
         return $this->readParen();
      default:
         $this->diag( DIAG_SYNTAX | DIAG_ERR, $this->scopeLexer->token->pos,
            "unexpected token: %d %s", $this->scopeLexer->tk,
            $this->scopeLexer->copyTokenText() );
         $this->bail();
      }
   }

   private function readNull(): \Node {
      $this->testKw( TK_NULL );
      $pointer = new \NullPointer();
      $this->readTk();
      return $pointer;
   }

   private function readSizeof(): \Node {
      $this->testKw( TK_SIZEOF );
      $sizeof = new \Sizeof();
      $sizeof->pos = $this->scopeLexer->token->pos;
      $this->readTk();
      $this->testTk( TK_LPAREN );
      $this->readTk();
      $sizeof->expr = $this->readExpr();
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $sizeof;
   }

   private function readModPrefix(): \Node {
      $this->testKw( TK_MODKW );
      $this->scopeLexer->token->type = TK_ID;
      $this->scopeLexer->tk = TK_ID;
      return $this->readNameUsage();
   }

   private function readNameUsage(): \NameUsage {
      $mutable = false;
      if ( $this->scopeLexer->tkPotentialKw() === TK_MUT ) {
         $mutable = true;
         $this->readTk();
      }
      $this->testTk( TK_ID );
      $pos = $this->scopeLexer->token->pos;
      $name = $this->scopeLexer->copyTokenText();
      $moduleNamePos = $pos;
      $moduleName = '';
      $this->readTk();
      if ( $this->scopeLexer->tk == TK_COLONCOLON ) {
         $this->readTk();
         $this->testTk( TK_ID );
         $moduleName = $name;
         $name = $this->scopeLexer->copyTokenText();
         $pos = $this->scopeLexer->token->pos;
         $this->readTk();
      }
      $args = [];
      $argsListSpecified = false;
      if ( $this->scopeLexer->tk == TK_LBRAC ) {
         $argsListSpecified = true;
         $this->readTk();
         if ( $this->scopeLexer->tk != TK_RBRAC ) {
            $args = $this->readArgumentList( TK_RBRAC );
         }
         $this->testTk( TK_RBRAC );
         $this->readTk();
      }

      $usage = new \NameUsage();
      $usage->pos = $pos;
      $usage->name = $name;
      $usage->moduleName = $moduleName;
      $usage->moduleNamePos = $moduleNamePos;
      $usage->args = $args;
      $usage->argsListSpecified = $argsListSpecified;
      $usage->mutable = $mutable;
      return $usage;
   }

   private function readIntegerLiteral(): \IntegerLiteral {
      $this->testTk( TK_INTEGER_LITERAL );
      $literal = new \IntegerLiteral();
      $literal->pos = $this->scopeLexer->token->pos;
      $literal->value = ( int ) $this->scopeLexer->copyTokenText();
      $this->readTk();
      return $literal;
   }

   private function readStringLiteral(): \StringLiteral {
      $this->testTk( TK_STRING_LITERAL );
      $literal = new \StringLiteral();
      $literal->value = $this->scopeLexer->copyTokenText();
      $literal->index = $this->task->internString( $literal->value );
      $this->readTk();
      return $literal;
   }

   private function readBoolLiteral(): \BoolLiteral {
      $value = 0;
      if ( $this->scopeLexer->tkPotentialKw() == TK_TRUE ) {
         $this->readTk();
         $value = 1;
      }
      else {
         $this->testKw( TK_FALSE );
         $this->readTk();
      }
      $literal = new \BoolLiteral();
      $literal->value = $value;
      return $literal;
   }

   private function readTypeLiteral(): \TypeLiteral {
      $this->testTk( TK_COLON );
      $this->readTk();
      $this->testTk( TK_LBRAC );
      $this->readTk();
      $typeExprParser = new TypeExprParser( $this->task, $this->user,
         $this->lexer, $this->scopeLexer, $this->module );
      $literal = new \TypeLiteral();
      $literal->typeExpr = $this->readConstantExpr();
      $this->testTk( TK_RBRAC );
      $this->readTk();
      return $literal;
   }

   private function readParen(): \Node {
      $this->testTk( TK_LPAREN );
      $paren = new \Tuple();
      $this->readTk();
      if ( $this->scopeLexer->tk !== TK_RPAREN ) {
         $paren->args = $this->readArgList();
      }
      $this->testTk( TK_RPAREN );
      $this->readTk();
      return $paren;
   }
}
