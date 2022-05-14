<?php

declare( strict_types = 1 );

/**
 * Paths of common places used by the project.
 */
define( 'PROJECT_DIR', dirname( dirname( __FILE__ ) ) );
define( 'SRC_DIR', PROJECT_DIR . '/src' );
define( 'LEXING_DIR', SRC_DIR . '/Lexing' );
define( 'PARSING_DIR', SRC_DIR . '/Parsing' );
define( 'CHECKING_DIR', SRC_DIR . '/Checking' );
define( 'TYPING_DIR', SRC_DIR . '/Typing' );
define( 'CODEGEN_DIR', SRC_DIR . '/Codegen' );
define( 'CTCE_DIR', SRC_DIR . '/Ctce' );
define( 'SAMPLE_DIR', PROJECT_DIR . '/sample' );
define( 'TOP_DIR', SAMPLE_DIR );
define( 'LIB_DIR', PROJECT_DIR . '/lib' );
define( 'STD_DIR', LIB_DIR . '/std' );
define( 'BUILD_DIR', PROJECT_DIR . '/build' );

require_once __DIR__ . '/../vendor/autoload.php';

require_once SRC_DIR . '/common.php';
require_once SRC_DIR . '/Options.php';
require_once SRC_DIR . '/User.php';
require_once SRC_DIR . '/Launcher.php';
require_once SRC_DIR . '/IncludePath.php';
require_once SRC_DIR . '/Ast.php';
require_once SRC_DIR . '/ItemFinder.php';
require_once SRC_DIR . '/Task.php';
require_once SRC_DIR . '/Assembler.php';
require_once LEXING_DIR . '/Token.php';
require_once LEXING_DIR . '/Position.php';
require_once LEXING_DIR . '/Lexer.php';
require_once LEXING_DIR . '/ScopeLexer.php';
require_once PARSING_DIR . '/Parser.php';
require_once PARSING_DIR . '/ModuleParser.php';
require_once PARSING_DIR . '/DeclParser.php';
require_once PARSING_DIR . '/ExprParser.php';
require_once PARSING_DIR . '/TypeExprParser.php';
require_once TYPING_DIR . '/Type.php';
require_once TYPING_DIR . '/Describer.php';
require_once TYPING_DIR . '/DescriberUsage.php';
require_once TYPING_DIR . '/Description.php';
require_once TYPING_DIR . '/TypeChecker.php';
require_once TYPING_DIR . '/Presenter.php';
require_once TYPING_DIR . '/PresenterUsage.php';
require_once TYPING_DIR . '/InstanceChecker.php';
require_once TYPING_DIR . '/InstanceCheckerUsage.php';
require_once TYPING_DIR . '/SamenessChecker.php';
require_once TYPING_DIR . '/AnonymousUnion.php';
require_once CHECKING_DIR . '/Value.php';
require_once CHECKING_DIR . '/BuiltinModule.php';
require_once CHECKING_DIR . '/ModuleChecker.php';
require_once CHECKING_DIR . '/DeclChecker.php';
require_once CHECKING_DIR . '/ExprChecker.php';
require_once CHECKING_DIR . '/SimpleExprChecker.php';
require_once CHECKING_DIR . '/TypeExprChecker.php';
require_once CHECKING_DIR . '/FuncCallChecker.php';
require_once CODEGEN_DIR . '/Php/CodeGenerator.php';
require_once CODEGEN_DIR . '/Oatir/CodeGenerator.php';
//require_once CODEGEN_DIR . '/X64/phase.php';
require_once CODEGEN_DIR . '/C/CodeGenerator.php';
require_once CODEGEN_DIR . '/CAst/CodeGenerator.php';
require_once SRC_DIR . '/Ctce/Evaluator.php';
require_once SRC_DIR . '/Scope.php';

Launcher::run( $argc, $argv );
