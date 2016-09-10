#include <winapi/module.h>

#ifdef _WIN64
#error "Inline assembly is not available on 64 bit"
#endif

#define WIN32_LEAN_AND_MEAN
#include <windows.h>

namespace winapi {
	__declspec(naked) Module * WINAPI
		Module::Load(const char *) {
		__asm jmp[LoadLibraryA]
	}
	__declspec(naked) Module * WINAPI
		Module::Load(const char *, std::nullptr_t, LoadingFlags) {
		__asm jmp[LoadLibraryExA]
	}
	__declspec(naked) bool WINAPI
		Module::Free() {
		__asm jmp[FreeLibrary]
	}
	// handle name collision with Win32
	static auto Win32GetProcAddress = &::GetProcAddress;
	__declspec(naked) void * WINAPI
		Module::GetProcAddress(const char *) {
		__asm jmp[Win32GetProcAddress]
	}
}
