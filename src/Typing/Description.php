<?php

declare( strict_types = 1 );

namespace Typing;

enum Description {
   case ENUM;
   case ENUM_TYPE;
   case STRUCT;
   case INT;
   case INT64;
   case STR;
   case BOOL;
   case VOID;
   case PLACEHOLDER;
   case PTR;
   case REF;
   case NULLPTR;
   case TRAIT;
   case STRUCT_TYPE;
   case STRUCT_INFO;
   case UNKNOWN;
   case NEVER;
   case ERR;
   case UNCHECKED_INT;
   case VALUE;
   case GENERIC;
   case TYPE;
   case BINDING;
}
