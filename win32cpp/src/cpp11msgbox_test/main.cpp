#include <msgbox.h>

int main() {
	using namespace winapi;

	auto choice = MsgBox(nullptr, "Yes or No?", "ANSI version", MB::ICONQUESTION | MB::YESNO);
	switch (choice) {
	case ID::YES:
		MsgBox(nullptr, L"Yes!", L"WIDE version", MB::ICONEXCLAMATION);
		break;
	case ID::NO:
		MsgBox(nullptr, L"No!", L"WIDE version", MB::ICONEXCLAMATION);
		break;
	}
}
