#include <winapi/module.h>

using namespace winapi;

int main() {
	Module * lib1 = Module::Load("user32.dll");
	Module * lib2 = Module::Load("user32.dll", nullptr, LoadingFlags::DONT_RESOLVE_DLL_REFERENCES);
	if (lib1 && lib2) {
		void* addr1 = lib1->GetProcAddress("MessageBoxA");
		void* addr2 = lib2->GetProcAddress("MessageBoxA");

		if (addr1 != nullptr && addr1 == addr2) {
			typedef int(WINAPI * MsgBoxA)(void*, const char*, const char*, uint32_t);
			auto msgbox = (MsgBoxA) addr1;
			msgbox(nullptr, "It works!", "Ok", 0);
		}

		lib1->Free();
		lib2->Free();
	}
}
