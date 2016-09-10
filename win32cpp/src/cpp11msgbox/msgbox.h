#pragma once

#include <cstdint>

namespace winapi {
	class Window;
	enum class MB : std::uint32_t;
	enum class ID : int;

	ID __declspec(dllimport) __stdcall MsgBox(Window * owner, const char * text, const char * title, MB type);
	ID __declspec(dllimport) __stdcall MsgBox(Window * owner, const wchar_t * text, const wchar_t * title, MB type);

	enum class MB : std::uint32_t {
		OK = 0x00000000L,
		OKCANCEL = 0x00000001L,
		ABORTRETRYIGNORE = 0x00000002L,
		YESNOCANCEL = 0x00000003L,
		YESNO = 0x00000004L,
		RETRYCANCEL = 0x00000005L,

		ICONHAND = 0x00000010L,
		ICONQUESTION = 0x00000020L,
		ICONEXCLAMATION = 0x00000030L,
		ICONASTERISK = 0x00000040L,
	};

	// MB values can be combined together
	// so we provide an OR operator for that purpose
	// (constexpr would be better but it's not
	// officially supported by VC++ 2013)
	inline MB operator|(MB l, MB r) {
		return static_cast<MB>(
			static_cast<std::uint32_t>(l) |
			static_cast<std::uint32_t>(r));
	}

	// Dialog Box Command IDs
	enum class ID : int {
		OK = 1,
		CANCEL = 2,
		ABORT = 3,
		RETRY = 4,
		IGNORE = 5,
		YES = 6,
		NO = 7,
	};
}
