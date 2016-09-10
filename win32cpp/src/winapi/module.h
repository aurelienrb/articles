#pragma once

#include <cstdint>

#define WINAPI __stdcall

namespace winapi {
	enum class LoadingFlags : std::uint32_t {
		DONT_RESOLVE_DLL_REFERENCES = 0x00000001,
		LOAD_LIBRARY_AS_DATAFILE = 0x00000002,
		LOAD_WITH_ALTERED_SEARCH_PATH = 0x00000008,
		LOAD_IGNORE_CODE_AUTHZ_LEVEL = 0x00000010,
		LOAD_LIBRARY_AS_IMAGE_RESOURCE = 0x00000020,
		LOAD_LIBRARY_AS_DATAFILE_EXCLUSIVE = 0x00000040,
		LOAD_LIBRARY_REQUIRE_SIGNED_TARGET = 0x00000080,
		LOAD_LIBRARY_SEARCH_DLL_LOAD_DIR = 0x00000100,
		LOAD_LIBRARY_SEARCH_APPLICATION_DIR = 0x00000200,
		LOAD_LIBRARY_SEARCH_USER_DIRS = 0x00000400,
		LOAD_LIBRARY_SEARCH_SYSTEM32 = 0x00000800,
		LOAD_LIBRARY_SEARCH_DEFAULT_DIRS = 0x00001000,
	};

	// Loading flags can be combined together so we provide
	// an OR operator for that purpose
	// (can't use constexpr as it's not supported by VC++ 2013)
	inline LoadingFlags
		operator|(LoadingFlags left, LoadingFlags right) {
		return static_cast<LoadingFlags>(
			static_cast<std::uint32_t>(left) |
			static_cast<std::uint32_t>(right));
	}

	class Module {
	public:
		static Module * WINAPI Load(const char * fileName);
		static Module * WINAPI Load(const char * fileName, std::nullptr_t, LoadingFlags flags);
		bool WINAPI Free();
		void * WINAPI GetProcAddress(const char * procName);

	private:
		~Module() = delete;
	};
	static_assert(sizeof(Module) == 1, "Not empty!");
} // namespace win32
