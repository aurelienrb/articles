#define WIN32_LEAN_AND_MEAN
#include <windows.h>

class Module {
public:
	static Module * Load(const char * fileName) {
		return reinterpret_cast<Module*>(
			::LoadLibraryA(fileName));
	}
	bool Free() {
		return ::FreeLibrary(
			reinterpret_cast<HMODULE>(this)) == TRUE;
	}
	template<typename T>
	T GetProcAddress(const char * procName) {
		return (T) ::GetProcAddress(
			reinterpret_cast<HMODULE>(this),
			procName);
	}

	operator HMODULE() {
		return reinterpret_cast<HMODULE>(this);
	}

private:
	~Module() = delete;
};

#ifdef _WIN64
const char * message = "Hello Win64 from C++!";
#else
const char * message = "Hello Win32 from C++!";
#endif

int main() {
	Module * lib = Module::Load("user32.dll");
	if (lib) {
		typedef int (WINAPI * MsgBoxA)(HWND hWnd, LPCSTR lpText, LPCSTR lpCaption, UINT uType);
		auto fn = lib->GetProcAddress<MsgBoxA>("MessageBoxA");
		if (fn) {
			fn(nullptr, message, "Ok!", MB_OK);
		}
		lib->Free();
	}
}
