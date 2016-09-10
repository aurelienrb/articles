#include <winapi/module.h>

using namespace winapi;

#ifdef _WIN64
const char * message = "Hello Win64 from C++!";
#else
const char * message = "Hello Win32 from C++!";
#endif

int main() {
	Module * lib = Module::Load("user32.dll");
	if (lib) {
		typedef int(WINAPI * MsgBoxA)(void *, const char *, const char *, std::uint32_t);
		auto msgbox = (MsgBoxA) lib->GetProcAddress("MessageBoxA");
		if (msgbox) {
			msgbox(nullptr, message, "Ok!", 0);
		}
		lib->Free();
	}
}
