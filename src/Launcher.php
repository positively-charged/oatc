<?php

declare( strict_types = 1 );

use PositivelyCharged\CmdParser;

class Launcher {
   private int $argc;
   private array $argv;
   private Options $options;

   private function __construct( int $argc, array $argv ) {
      $this->argc = $argc;
      $this->argv = $argv;
   }

   private function runCompiler(): void {
      try {
         $user = new User( $this->argc, $this->argv );
         $this->performTask( $user );
         exit( EXIT_SUCCESS );
      } catch ( Unreachable $err ) {
         $err->show();
         exit( EXIT_FAILURE );
      }
   }

   private function performTask( User $user ): void {
      $options = $this->readOptions( $user );

      /*
      $lexer = new Lexer( $contents );
      while ( $lexer->readToken() != TK_END ) {
         switch ( $lexer->tk ) {
         case TK_ID:
            printf( "%d %s\n", $lexer->tk, $lexer->copyTokenText() );
            break;
         default:
            printf( "%d %s\n", $lexer->tk, $lexer->copyTokenText() );
            //printf( "%d\n", $lexer->tk );
         }
      }*/

      try {
         $this->compileFile( $user, $options );
         /*
         $task = new Task();
         $task->user = $user;

         // Load prelude.
         self::loadPrelude( $task, $include_path );

         $lexer = new Lexer( $user, $file, $contents );
         //$lexer->dumpTokenStream( $user );

         $parser = new ModuleParser( $task, $lexer, $include_path,
            $task->prelude );
         $parser->parse();
         //var_dump( $module );

         $checker = new ModuleChecker( $task );
         $checker->check();

         $generator = new Codegen\CAst\CodeGenerator( $task->module );
         $generator->publish( SAMPLE_DIR . sprintf( '/%s.c', TEST_FILE ) );
         */

         //$generator = new Codegen\C\CodeGenerator( $module );
         //$generator->publish( SAMPLE_DIR . sprintf( '/%s.c', TEST_FILE ) );

         //$output_file = SAMPLE_DIR . sprintf( '/%s.s', TEST_FILE );
         // $generator = new Codegen\OatIr\Walker( $module );
         // $generator->publish();
         //  $x64_generator = new Codegen\X64\CodeGenerator( $module );
         // $x64_generator->publish( $output_file );
      } catch ( SyntaxError $e ) {
         exit( EXIT_FAILURE );
      }
   }

   private function readOptions( User $user ): Options {
      $options = new Options();
      $args = new CmdParser\Parser( array_slice( $this->argv, 1 ) );
      while ( $args->readOption() ) {
         switch ( $args->option ) {
         case '--defer-errs':
            $options->deferErrs = true;
            break;
         default:
            $user->diag( DIAG_ERR, null,
               "unknown command-line option `{$args->option}` specified" );
            $user->bail();
         }
      }

      try {
         $options->file = $args->readRequiredValue();
      }
      catch ( Exception ) {
         $user->diag( DIAG_ERR, null,
            "missing sample file name" );
         $this->printUsage( $user );
         $user->bail();
      }

      $options->outputFile = $args->readOptionalValue( '' );

      $this->options = $options;
      return $options;
   }

   private function printUsage( User $user ): void {
      $user->diag( DIAG_NONE, null,
         "Usage: %s <file> [output-file]", $this->argv[ 0 ] );
   }

   private function compileFile( User $user, Options $options ): void {
      $contents = @file_get_contents( $options->file );
      if ( $contents === false ) {
         $user->diag( DIAG_ERR, null,
            "failed to read file: %s", $options->file );
         $user->bail();
      }

      $includePath = new IncludePath( dirname( $options->file ) );
      $includePath->addPrefix( 'pkg', SAMPLE_DIR );
      $includePath->addPrefix( 'lib', LIB_DIR );
      $includePath->addPrefix( 'std', STD_DIR );

      $lexer = new Lexing\Lexer( $user, $options->file, $contents );
      $scopeLexer = new Lexing\ScopeLexer( $lexer );
      # $lexer->dumpTokenStream( $user );

      $task = new Task();
      $task->user = $user;

      $builtinModule = new \Checking\BuiltinModule();
      $task->builtinModule = $builtinModule;
      $task->modules[] = $builtinModule->module;

      $this->loadPrelude( $task, $includePath );

      $parser = new Parse\ModuleParser( $lexer, $scopeLexer, $task, $includePath,
         $task->prelude );
      $parser->parse();

      foreach ( $task->modules as $loadedModule ) {
         $loadedModule->importedModules[] = $builtinModule->module;
      }

      $typeDescriber = new \Typing\Describer();
      $typePresenter = new \Typing\Presenter( $task, $typeDescriber );
      $samenessChecker = new \Typing\SamenessChecker( $builtinModule,
         $typeDescriber );
      $instanceChecker = new \Typing\InstanceChecker( $typeDescriber,
         $samenessChecker );

      $scopeList = new \Scope( $user, $task->module, $typeDescriber );
      $typeChecker = new \Typing\TypeChecker( $builtinModule,
         $typeDescriber );

      $user->showErrsAsWarnings = ( $options->deferErrs === true );
      $checker = new Checking\ModuleChecker( $task, $scopeList, $typeChecker,
         $typeDescriber, $typePresenter, $instanceChecker, $samenessChecker );
      $checker->check();
      if ( $user->isErrorsReported() && ! $options->deferErrs ) {
         exit( 1 );
      }

      $outputFile = $options->outputFile;
      if ( empty( $outputFile ) ) {
         $outputFile = BUILD_DIR . '/' .
            sprintf( 'testfile-%d.s', fileinode( $options->file ) );
      }

      //var_dump( 'output file: '. $outputFile );
      $generator = new \Codegen\Cast\CodeGenerator( $typeDescriber,
         $instanceChecker, $typePresenter, $task, $task->module );
      $generator->publish( $outputFile );

      //$generator = new Codegen\Php\CodeGenerator( $task, $typeChecker );
      //$generator->publish( $outputFile );

      #$generator = new Codegen\Oatir\CodeGenerator( $task, $typeChecker );
      #$archive = $generator->generateIr( $outputFile );

/*
      $generator = new Codegen\X64\CodeGenerator( $task, $typeChecker );
      $generator->publish( $outputFile );

      $assembler = new Assembler();
      $objectFile = substr( $outputFile, 0, -2 ) . '.o';
      $assembler->assemble( $outputFile, $objectFile );
*/
   }

   private function loadPrelude( Task $task,
      IncludePath $includePath ): void {
      $preludePath = sprintf( '%s/prelude.oat', STD_DIR );
      $contents = @file_get_contents( $preludePath );
      if ( $contents === false ) {
         printf( "failed to open prelude module\n" );
         exit( 1 );
      }
      $lexer = new \Lexing\Lexer( $task->user, $preludePath, $contents );
      $scopeLexer = new Lexing\ScopeLexer( $lexer );
      $parser = new \Parse\ModuleParser( $lexer, $scopeLexer, $task, $includePath );
      $parser->parse();
      $task->prelude = $task->module;
   }

   public static function run( int $argc, array $argv ): void {
      $launcher = new Launcher( $argc, $argv );
      $launcher->runCompiler();
   }
}
