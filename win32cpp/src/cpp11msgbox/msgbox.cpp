#pragma comment(lib, "user32.lib")
#define EXPORT_ALIAS(name, alias)\
	__pragma(comment(linker, "/export:" alias "=" name))

#ifdef _WIN64
	// 64 bits
#if _MSC_VER == 1900 // VC++ 2015
	EXPORT_ALIAS("MessageBoxA", "?MsgBox@winapi@@YA?AW4ID@1@PEAVWindow@1@PEBD1W4MB@1@@Z")
	EXPORT_ALIAS("MessageBoxW", "?MsgBox@winapi@@YA?AW4ID@1@PEAVWindow@1@PEB_W1W4MB@1@@Z")
#elif _MSC_VER == 1800 // VC++ 2013
	EXPORT_ALIAS("MessageBoxA", "?MsgBox@winapi@@YG?AW4ID@1@PAVWindow@1@PBD1W4MB@1@@Z")
	EXPORT_ALIAS("MessageBoxW", "?MsgBox@winapi@@YG?AW4ID@1@PAVWindow@1@PB_W1W4MB@1@@Z")
#else
	#error "This compiler version is not supported"
#endif
#else
	// 32 bits
	EXPORT_ALIAS("_MessageBoxA@16", "?MsgBox@winapi@@YG?AW4ID@1@PAVWindow@1@PBD1W4MB@1@@Z")
	EXPORT_ALIAS("_MessageBoxW@16", "?MsgBox@winapi@@YG?AW4ID@1@PAVWindow@1@PB_W1W4MB@1@@Z")
#endif
