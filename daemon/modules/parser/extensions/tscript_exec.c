/*

T-Script - EXEC EXTENSION
Copyright (C) 2004, Adrian Smarzewski <adrian@kadu.net>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

#include "tscript_exec.h"
#include "tscript_extensions.h"

#include <stdio.h>

tscript_value tscript_ext_exec(tscript_value arg)
{
	char* out;
	int res;
	FILE* child_out = popen(arg.data, "r");
	if (child_out == NULL)
		return tscript_value_create_error("Couldn't execute %s", arg.data);
	out = (char*)malloc(512); // FIXME
	res = fread(out, 1, 511, child_out);
	out[res]=0;
	pclose(child_out);
	return tscript_value_create(TSCRIPT_TYPE_STRING, out);
}

void tscript_ext_exec_init()
{
	tscript_add_extension("exec", tscript_ext_exec);
}

void tscript_ext_exec_close()
{
	tscript_remove_extension("exec");
}