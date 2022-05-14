<?php

declare( strict_types = 1 );

namespace Parse;

use IncludePath;
use User;
use Task;
use Module;
use Lexing;

class ModuleParser extends Parser {
   private Task $task;
   private ?Module $module;
   private ?Module $prelude;
   private IncludePath $includePath;
   private array $queuedLexers;

   public function __construct( Lexing\Lexer $lexer,
      Lexing\ScopeLexer $scopeLexer, Task $task,
      IncludePath $includePath, ?Module $prelude = null ) {
      parent::__construct( $task->user, $lexer, $scopeLexer );
      $this->task = $task;
      $this->module = null;
      $this->prelude = $prelude;
      $this->includePath = $includePath;
      $this->queuedLexers = [];
   }

   public function parse(): void {
      $module = new Module();
      $module->path = $this->lexer->file;
      $this->task->module = $module;
      $this->task->modules[] = $module;
      $this->parseModule( $module );
   }

   private function parseModule( Module $module ): void {
      // Prelude.
      if ( $this->prelude != null ) {
         $module->importedModules[] = $this->prelude;
      }

      #$pass1 = new Pass1Parser( $this->task, $this->lexer, $this->scope );
      #$pass1->readModule();


      //exit ( 1 );

      $prevModule = $this->module;
      $this->module = $module;
      $this->readTk();
      $this->parseModuleItemList();
      $this->module = $prevModule;
   }

   private function parseModuleItemList(): void {
      while ( $this->scopeLexer->tk != TK_END ) {
         $this->parseModuleItem();
      }
   }

   private function parseModuleItem(): void {
      $attrs = $this->readAttributes();
      $visible = $this->readVisibility();
      //switch ( $this->lexer->expectedKw() ) {
      switch ( $this->scopeLexer->tkPotentialKw() ) {
      case TK_MODKW:
         $this->readSubModule( $visible );
         break;
      case TK_IMPORT:
         $this->onImport( $visible );
         break;
      case TK_CONST:
      case TK_ENUM:
      case TK_VARIAN:
      case TK_STRUCT:
      case TK_TRAIT:
      case TK_COMPILETIME:
      case TK_IMPLEMENTS:
      case TK_FUN:
      case TK_VIRT:
      case TK_GEN:
         $declParser = new DeclParser( $this->task, $this->user, $this->lexer,
            $this->scopeLexer, $this->module );
         $object = $declParser->readDecl( $attrs, $visible );
         $this->module->items[] = $object;
         break;
      case TK_LET:
         $this->readLet( $attrs, $visible );
         break;
      case TK_ID:
         $this->readBinding( $attrs, $visible );
         break;
      case TK_NL:
         $this->readTk();
         break;
      default:
         $this->diag( DIAG_SYNTAX | DIAG_ERR, $this->lexer->token->pos,
            'unexpected token: `%s`', $this->lexer->copyTokenText() );
         $this->bail();
      }
   }

   private function readAttributes(): array {
      $attrs = [];
      while ( $this->lexer->tk == TK_AT ) {
         $attrs[] = $this->readAttribute();
      }
      return $attrs;
   }

   private function readAttribute(): \Attr {
      $this->testTk( TK_AT );
      $attr = new \Attr();
      $attr->pos = $this->lexer->token->pos;
      $this->readTk();
      $this->testTk( TK_LBRAC );
      $this->readTk();
      $this->testTk( TK_ID );
      $attr->name = $this->lexer->copyTokenText();
      $this->readTk();
      if ( $this->lexer->tk == TK_COLON ) {
         $this->readTk();
         $exprParser = new ExprParser( $this->task, $this->user, $this->lexer,
            $this->scopeLexer, $this->module );
         $attr->args = $exprParser->readArgumentList( TK_RBRAC );
      }
      $this->testTk( TK_RBRAC );
      $this->readTk();
      return $attr;
   }

   private function readVisibility(): bool {
      $visible = false;
      if ( $this->scopeLexer->tkPotentialKw() == TK_PUB ) {
         $visible = true;
         $this->readTk();
      }
      return $visible;
   }

   private function readLet( array $attrs, bool $visible ): void {
      $exprParser = new ExprParser( $this->task, $this->user, $this->lexer,
         $this->scopeLexer, $this->module );
      $binding = $exprParser->readLet();
      $this->testTk( TK_SEMICOLON );
      $this->readTk();
      $this->module->items[] = $binding;
   }

   private function readBinding( array $attrs, bool $visible ): void {
      $name = $this->readName();
      $constant = new \Constant();
      $constant->visible = $visible;
      $constant->pos = $name->pos;
      $constant->name = $name->value;
      $constant->name2 = $name;

      if ( $this->scopeLexer->tk === TK_COLON ) {
         $this->readTk();
         if ( $this->scopeLexer->tk !== TK_EQ ) {
            $declParser = new TypeExprParser( $this->task, $this->user,
               $this->lexer, $this->scopeLexer, $this->module );
            $constant->typeExpr = $declParser->readTypeExpr();
         }
         if ( $this->scopeLexer->tk === TK_EQ ) {
            $this->readTk();
            $exprParser = new ExprParser( $this->task, $this->user, $this->lexer,
               $this->scopeLexer, $this->module );
            $constant->value = $exprParser->readExpr();
         }
      }

      $this->testTk( TK_SEMICOLON );
      $this->readTk();

      $this->module->items[] = $constant;
   }

   private function readSubModule( bool $visible ): void {
      $this->testTk( TK_MODKW );
      $this->readTk();
      $this->testTk( TK_ID );
      $subModule = new \SubModule();
      $subModule->name = $this->lexer->copyTokenText();
      $subModule->visible = $visible;
      array_push( $this->module->subModules, $subModule );
      $this->readTk();
      $this->testTk( TK_SEMICOLON );
      $this->readTk();
   }

   private function onImport( bool $visible ): void {
      $import = $this->readImport( $visible );
      $this->performImport( $import );
   }

   private function readImport( bool $visible ): \Import {
      $this->testKw( TK_IMPORT );
      $import = new \Import();
      $import->pos = $this->scopeLexer->token->pos;
      $import->visible = $visible;
      $this->readTk();
      if ( $this->scopeLexer->tk == TK_LPAREN ) {
         $this->readTk();
         $import->selections = $this->readImportSelectionList();
         $this->testTk( TK_RPAREN );
         $this->readTk();
      }
      else {
         $selection = $this->readImportSelection();
         array_push( $import->selections, $selection );
      }
      $this->testTk( TK_SEMICOLON );
      $this->readTk();
      return $import;
   }

   private function readImportSelectionList(): array {
      $selections = [];
      while ( true ) {
         $selection = $this->readImportSelection();
         array_push( $selections, $selection );
         if ( $this->scopeLexer->tk == TK_COMMA ) {
            $this->readTk();
            if ( $this->scopeLexer->tk == TK_RPAREN ) {
               break;
            }
         }
         else {
            break;
         }
      }
      return $selections;
   }

   private function readImportSelection(): \ImportSelection {
      $selection = new \ImportSelection();
      $this->readImportAlias( $selection );
      $selection->path = $this->readPath();
      if ( $this->scopeLexer->tk == TK_COLONCOLON ) {
         $this->readTk();
         if ( $this->scopeLexer->tk == TK_LPAREN ) {
            $this->readTk();
            $selection->selections = $this->readImportSelectionList();
            $this->testTk( TK_RPAREN );
            $this->readTk();
         }
         else {
            $this->testTk( TK_STAR );
            $this->readTk();
            $selection->glob = true;
         }
      }
      return $selection;
   }

   private function readImportAlias( \ImportSelection $selection ): void {
      if ( $this->scopeLexer->tk == TK_ID && $this->scopeLexer->peek() == TK_EQ ) {
         $selection->alias = $this->readName();
         $this->testTk( TK_EQ );
         $this->readTk();
      }
   }

   private function readPath(): \Path {
      $useCurrentModule = false;
      $head = null;
      $tail = null;
      while ( true ) {
         if ( $head == null && $this->scopeLexer->tk == TK_MODKW ) {
            $useCurrentModule = true;
            $this->readTk();
         }
         else {
            $this->testTk( TK_ID );
            $component = new \PathComponent();
            $component->pos = $this->scopeLexer->token->pos;
            $component->name = $this->scopeLexer->copyTokenText();
            if ( $head ) {
               $tail->next = $component;
            }
            else {
               $head = $component;
            }
            $tail = $component;
            $this->readTk();
         }
         if ( $this->scopeLexer->tk == TK_SLASH ) {
            $component->shortcut = true;
            $this->readTk();
         }
         if ( $this->scopeLexer->tk == TK_COLONCOLON &&
            $this->scopeLexer->peek() == TK_ID ) {
            $this->readTk();
         }
         else {
            break;
         }
      }
      $path = new \Path();
      $path->head = $head;
      $path->tail = $tail;
      $path->useCurrentModule = $useCurrentModule;
      return $path;
   }

   private function performImport( \Import $import ): void {
      foreach ( $import->selections as $selection ) {
         $this->importSelection( $import, $selection, null );
      }
      array_push( $this->module->imports, $import );
   }

   private function importSelection( \Import $import,
      \ImportSelection $selection, ?\ImportSelection $parent ): void {
      $this->importPath( $selection, $parent );
      if ( $selection->glob ) {
         if ( ! in_array( $selection->module,
            $this->module->importedModules ) ) {
            array_push( $this->module->importedModules, $selection->module );
            if ( $import->visible ) {
               array_push( $this->module->visibleImportedModules,
                  $selection->module );
            }
         }
      }
      else {
         foreach ( $selection->selections as $nestedSelection ) {
            $this->importSelection( $import, $nestedSelection, $selection );
         }
      }
   }

   private function getSubModule( Module $module, string $name ): ?\SubModule {
      foreach ( $module->subModules as $subModule ) {
         if ( $name == $subModule->name ) {
            return $subModule;
         }
      }

      // Module declarations do not need to be explicitly specified. As long as
      // a module file exists, we have a module. When a module declaration is
      // implied, private visibility is assumed for the module.
      $path = sprintf( '%s/%s.oat', dirname( $module->path ), $name );
      if ( file_exists( $path ) ) {
         $subModule = new \SubModule();
         $subModule->name = $name;
         array_push( $this->module->subModules, $subModule );
         return $subModule;
      }

      return null;
   }

   private function isSubModule( Module $module, Module $subModule ): bool {
      $dir = dirname( $module->path );
      return ( strncmp( $dir, $subModule->path, strlen( $dir ) ) == 0 );
   }

   private function importPath( \ImportSelection $selection,
      ?\ImportSelection $parent ): void {
      $path = $selection->path->head;
      if ( $parent ) {
         $module = $parent->module;
      }
      else {
         if ( $selection->path->useCurrentModule ) {
            $module = $this->module;
            $selection->module = $module;
         }
         else {
            $dir = '';
            if ( $path->shortcut ) {
               switch ( $path->name ) {
               case 'top': $dir = TOP_DIR; break;
               case 'std': $dir = STD_DIR; break;
               case 'lib': $dir = LIB_DIR; break;
               case 'pkg': $dir = dirname( $this->module->path ); break;
               default:
                  $this->user->diag( DIAG_ERR, $path->pos,
                     "unknown shortcut `%s`", $path->name );
                  $this->user->bail();
               }
            }

            // When no shortcut is specified, assume the current package.
            if ( $dir == '' ) {
               $dir = dirname( $this->module->path );
            }
            else {
               $path = $path->next;
            }

            $this->importPackage( $selection, $dir );
            $module = $selection->module;
         }
      }
      $prevPath = $path;
      while ( $path != null ) {
      /*
         if ( $path->next == null && ! $selection->moduleExpected ) {
            break;
         }
      */

         if ( ! $module->package ) {
            // Last item of a path might be a non-module item.
            if ( $path->next == null ) {
               $item = new \ImportItem();
               $item->name = $path->name;
               $item->pos = $path->pos;
               $selection->item = $item;
               $selection->module = $module;
               break;
            }
            $this->user->diag( DIAG_ERR, $prevPath->pos,
               "module `%s` not a package",
               $prevPath->name );
            $this->user->bail();
         }

         // For implicit package modules, it is implied the package module
         // contains public module declarations for each of its children.
         if ( ! $module->implicit ) {
            $subModule = $this->getSubModule( $module, $path->name );
            if ( $subModule && ! ( $subModule->visible ||
               $this->isSubModule( $module, $this->module ) ) ) {
               $this->user->diag( DIAG_ERR, $path->pos,
                  "module `%s` is private",
                  $path->name );
               $this->user->bail();
            }
         }

         $dir = dirname( $module->path );
         $subModulePath = sprintf( '%s/%s.oat', $dir, $path->name );
         // FIXME: Possible race condition: we first check if a module file
         // exists; if it does, we read it. Otherwise, we check if a package
         // directory exists; if it does, we read it. Now what happens if we
         // read a package for a path component on one import, then on a later
         // import, a module file gets created on the file system for the same
         // path component? We would end up creating both a package and a
         // module for the same path component.
         $subModule = $this->task->findLoadedModule( $subModulePath );
         if ( $subModule == null ) {
            if ( file_exists( $subModulePath ) ) {
               $this->importModule( $selection, $subModulePath );
            }
            else {
               $packagePath = sprintf( '%s/%s', $dir, $path->name );
               if ( is_dir( $packagePath ) ) {
                  $this->importPackage( $selection, $packagePath );
               }
               else {
                  // The item might be something not a module
                  if ( $parent && ! $path->next && ! $selection->glob &&
                     empty( $selection->selections ) ) {
                     return;
                  }
                  else {
                     $this->user->diag( DIAG_ERR, $path->pos,
                        "module `%s` does not contain a sub-module called `%s`",
                        $module->path, $path->name );
                     $this->user->bail();
                  }
               }
            }
         }
         else {
            $selection->module = $subModule;
         }

         $module = $selection->module;
         $prevPath = $path;
         $path = $path->next;
      }
   }

   private function importPackage( \ImportSelection $selection,
      string $dir ): void {
      $path = sprintf( '%s/pkg.oat', $dir );
      $module = $this->task->findLoadedModule( $path );
      if ( $module == null ) {
         if ( file_exists( $path ) ) {
            $this->importModule( $selection, $path );
         }
         else {
            $this->importImplicitPackageModule( $selection, $path );
         }
         $selection->module->package = true;
      }
      else {
         $selection->module = $module;
      }
   }

   private function importImplicitPackageModule( \ImportSelection $selection,
      string $assumedPath ): void {
      $module = new Module();
      $module->path = $assumedPath;
      $module->implicit = true;
      array_push( $this->task->modules, $module );
      $selection->module = $module;
   }

   private function importModule( \ImportSelection $selection,
      string $path ): void {
      $contents = file_get_contents( $path );
      if ( $contents === false ) {
         printf( "failed to open sample file\n" );
         exit( 1 );
      }
      $this->queuedLexers[] = $this->scopeLexer;

      $lexer = new \Lexing\Lexer( $this->user, $path, $contents );
      $scopeLexer = new \Lexing\ScopeLexer( $lexer );
      $this->lexer = $lexer;
      $this->scopeLexer = $scopeLexer;

      $module = new Module();
      $module->path = $this->scopeLexer->lexer->file;
      $selection->module = $module;
      $this->task->modules[] = $module;

      $this->parseModule( $module );

      $this->scopeLexer = array_pop( $this->queuedLexers );
      $this->lexer = $this->scopeLexer->lexer;
      $this->tk = $this->scopeLexer->tk;
   }
}
