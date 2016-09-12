<?php

$PAGES = array(
1 => "<h2>Introduction</h2>
<p>Windows objects are abstractions used to manipulate various system resources. Their inner structure is hidden to the user via surrogate objects called handles which act as opaque identifiers from the client point of view. Implementation details for these objects are thus inaccessible, forcing the user to use the provided C interface<note>Abstracting the memory address of the objects is not only a matter of good encapsulation but a necessity when these objects must be shared among several processes at the same time, each one having its own virtual memory space.</note>.</p>
<p>The present article aims at create an alternative interface that is not based on C functions and opaque handles but on C++ classes corresponding to the system objects. Such classes must be void of any implementation code, and be seen as imported from the system libraries. The goal is really to expose a new interface from an existing implementation without touching it!</p>
<p>In the first part we will focus on the technical aspects of that challenge, mostly dealing with low level C++ and system programming. In the second part, we will apply the resulting methodology to various system objects to see Win32 in a new light. Finally, we will try to extricate valuable lessons from that experiment.</p>

<p class=\"important\">This articles tries to take advantage of the new features introduced by C++11. As a result, Visual C++ 2013 is required to successfully compile the code (using VC++ 2012 will not work).<br /><br />
Please note as well the source code presented in this article is often incomplete (in particular, there is no error management) to make it more concise.</p>",

10 => "<h2>Part I - Technical principles</h2>
<blockquote>
<p class=\"quote\">The more the marbles wastes, the more the statue grows.</p>
<footer>- Michelangelo</footer>
</blockquote>
<p>There are various ways and strategies when it comes to wrap some existing C code in C++. Often, it involves tracking some kind of unique identifier that must be given back to the C library:</p>
<cpp>#include <stdio.h>

int main() {
    FILE *handle = fopen(\"test.txt\", \"w\");
    fprintf(handle, \"Hello World!\");
    fclose(handle);
    return 0;
}</cpp>
<p>In the above example, we can identify that the <tt>FILE</tt> object has a constructor named <tt>fopen</tt>, a destructor named <tt>fclose</tt> and a member function named <tt>fprintf</tt>. Let's group them together in a C++ class.</p>
<cpp>#include <cstdio>

class CFile {
public:
    explicit CFile(const char * fileName,
                   const char * fileMode) {
        handle = std::fopen(fileName, fileMode);
    }
    ~CFile() {
        std::fclose(handle);
    }</cpp>",

12 => "<cpp>    void print(const char * text) {
        std::fprintf(handle, text);
    }
private:
    CFile(const CFile &) = delete; // non copyable
    CFile & operator=(const CFile &) = delete;

    FILE * handle;
};

int main() {
    CFile file(\"test.txt\", \"w\");
    file.print(\"Hello World!\");
}</cpp>
<p>This is a very classic way to wrap an existing code in C++. It is based on the <abbr title=\"Resource Acquisition Is Initialization\">RAII</abbr> idiom and, as a side effect, the resulting class is designed to be directly manipulated (value semantic) instead of being indirectly accessed through a pointer (reference semantic).</p>
<p>It's a major change to the original C interface that it is worth mentioning<note>Moreover, we had to make our class non copyable because the underlying FILE object is not copyable. This is somewhat in conflict with the introduced value semantic.</note>. The CFile class is actually more acting like a proxy to the FILE object than a wrapper:</p>
<img class=\"centered\" width=\"418\" height=\"133\" src=\"cfile-layer.png\" />",

13 => "<p>In such a design the C++ code is completely unknown and unrelated to the C library space. And this is not what we want to do in this article. Our goal is to expose a C++ interface from an existing C library without touching it:</p>
<img class=\"centered\" width=\"418\" height=\"126\" src=\"cfile-nolayer.png\" />
<p>It might sound unfeasible or at least very complicated since the library wasn't designed for that purpose. But it is actually quite simple: just remove the need of a C++ proxy instance. And that's what we are going to see right now.</p>
<h3>Removing the data layer</h3>
<p>If we look again at the original C code:</p>
<cpp>int main() {
    FILE * handle = fopen(\"test.txt\", \"w\");
    fprintf(handle, \"Hello World!\");
    fclose(handle);
    return 0;
}</cpp>
<p>We might realize that the file handle is being used in a similar fashion than the hidden <tt>this</tt> pointer of a C++ class instance. Therefore, the following question might arise: what about casting the FILE pointer directly to a CFile object?</p>",

14 => "<cpp>class CFile {
public:
    static CFile * open(const char * fileName,
                        const char * fileMode) {
        return reinterpret_cast<CFile*>(
            std::fopen(fileName, fileMode));
    }
    void close() {
        std::fclose(reinterpret_cast<FILE*>(this));
    }
    void print(const char * text) {
        std::fprintf(reinterpret_cast<FILE*>(this), text);
    }
};

int main() {
    CFile * file = CFile::open(\"test.txt\", \"w\");
    if (file) {
        file->print(\"Hello World!\");
        file->close();
    }
}</cpp>
<p>It works, on both Windows and Linux. If we compare the compiled code of the C and C++ versions with optimizations turned on, it is strictly the same<note>The resulting exe is different because the second version embeds the code of the CFile class, but when optimizations are turned on, the assembly code generated for the main function is the same in both version.</note>. It is hence possible to have two distinct interfaces corresponding to the same binary implementation. Even if it's a side effect of compiler optimization, that's a good start!</p>
<p>The presence of <tt>reinterpret_cast</tt> tends to raise eyebrows, and that's for a good reason: it is often used to write illegal C++ code. In effect, addressing the same memory address from two different data types (other than [unsigned] char) at the same time is not guaranteed by the standard because of possible aliasing effects.</p>",

15 => "<p>However, in our case, we do not try to dereference the casted pointer. It was created only for syntactic purpose, and is casted back to the correct type each time it is needed. Therefore, this use of <tt>reinterpret_cast</tt> is perfectly valid and harmless.</p>
<p>Anyway, that's just temporary code as we will see later how to get rid of all casts. But before that, let's see how we can apply the same trick on Win32 handles.</p>
<h3>Hacking Win32 handles</h3>
<cpp>#define WIN32_LEAN_AND_MEAN
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
};

// pointer to the MessageBox (ANSI) function
typedef int (WINAPI * MsgBox)(
    HWND hWnd, LPCSTR lpText, LPCSTR lpCaption, UINT uType);</cpp>",

17 => "<cpp>#ifdef _WIN64
    const char * message = \"Hello Win64 from C++!\";
#else
    const char * message = \"Hello Win32 from C++!\";
#endif

int main() {
    Module * lib = Module::Load(\"user32.dll\");
    if (lib) {
        auto fn = lib->GetProcAddress<MsgBox>(\"MessageBoxA\");
        if (fn) {
            fn(nullptr, message, \"Ok!\", MB_OK);
        }
        lib->Free();
    }
}</cpp>
<p>It can be tested from the VS2013 x86 command prompt:</p>
<pre>cl.exe /MD hacking_handles.cpp /Fehacking_handles-x86.exe</pre>
<img class=\"centered\" width=\"189\" height=\"154\" src=\"hello-win32.png\" />
<p>And from the VS2013 x64 command prompt:</p>
<pre>cl.exe /MD hacking_handles.cpp /Fehacking_handles-x64.exe</pre>",

16 => "<img class=\"centered\" width=\"189\" height=\"154\" src=\"hello-win64.png\" />

<p>As shown above, the <tt>this</tt> pointer has been successfully mapped on the HMODULE handle. That's because on both Win32 and Win64, handle types are defined as opaque pointers, making their size equal to the size of a pointer. This is the first requirement of that trick<note>Actually, the size of the handle type could be less than the size of a pointer depending on how the calling convention promotes types.</note>.</p>
<p>The second requirement is to never dereference the <tt>this</tt> pointer. For that reason our classes must always be completely empty, which means of course no data member, but also no virtual feature (to avoid the virtual table pointer).</p>
<h3>Adjusting the object model</h3>
<p>Instances of such classes can be neither instantiated nor destroyed directly by the user: they belong to Win32, and as a result they do not follow the default C++ object model. To reflect that important point in our design, we will use the following WinObject class as a common base for our future class hierarchy.</p>
<cpp>class WinObject {
private:
    WinObject() = delete;
    ~WinObject() = delete;
    WinObject(const WinObject &) = delete;
    WinObject & operator=(const WinObject &) = delete;
};</cpp>",

18 => "<p>The default constructor and the destructor are private and even marked as deleted (C++11 feature) to reflect the fact we don't have access to them (they are not exposed by Win32). We also forbid object copy to strengthen the reference semantic of theses objects.</p>
<p>As a result WinObject would normally become sealed: it would be impossible to instantiate a child class. But in our case, we will manipulate plenty of valid children instances. It's as if it was sealed for the users but not for the package it belongs to!</p>
<p>But what about inheritance? An object size can't be zero even if it's an empty class. Therefore there is a risk to alter the layout of the children classes which then would make the compiler adjust the <tt>this</tt> pointer accordingly. In other words: it would invalidate our trick!</p>
<cpp>class A : public WinObject {
};
class B : public A {
};

// should always succeed
static_assert(sizeof(WinObject) == 1, \"Not empty!\");

// susceptible to fail!
static_assert(sizeof(A) == 1, \"Not empty!\");
static_assert(sizeof(B) == 1, \"Not empty!\");</cpp>
<p>It appears to work fine thanks to the Empty Base Optimization<note>VC++ supports EBO since many years. It is known to have been restricted to single inheritance but even though that's fine for us.</note>. Such an optimization is possible because the standard says the base class sub-object <i>may</i> have zero size<note>1.8/5</note>... or may not!</p>
<p>In other terms we are in the implementation defined gray zone. But since we target quite modern compilers (C++11) that's a reasonable lelvel of risk. And the <tt>static_assert</tt> is here to detect any problem.</p>",

19 => "<h3>Removing the C headers dependency</h3>
<p class=\"important\">Starting from now, the code will be specific to Win32 (x86). The support of Win64 (x64) will be reintroduced later. </p>
<p>To move closer to a pure C++ interface, we need to get rid of the Win32 C headers. We are now going to clearly separate the interface from its implementation in such a way it does not depend on Win32 types.</p>
<cpp>#ifndef WINAPI_MODULE_H
#define WINAPI_MODULE_H

#include <cstdint>
#include \"winobject.h\"

namespace winapi {

enum class LoadingFlags : std::uint32_t {
    DONT_RESOLVE_DLL_REFERENCES         = 0x00000001,
    LOAD_LIBRARY_AS_DATAFILE            = 0x00000002,
    LOAD_WITH_ALTERED_SEARCH_PATH       = 0x00000008,
    // more flags...
    LOAD_LIBRARY_SEARCH_SYSTEM32        = 0x00000800,
    LOAD_LIBRARY_SEARCH_DEFAULT_DIRS    = 0x00001000,
};

// Loading flags can be combined together so we provide
// an OR operator for that purpose
// (can't use constexpr as it's not supported by VC++ 2013)
inline LoadingFlags
operator|(LoadingFlags left, LoadingFlags right) {
    return static_cast<LoadingFlags>(
        static_cast<std::uint32_t>(left) |
        static_cast<std::uint32_t>(right));
}</cpp>",

20 => "<cpp>class Module : public WinObject {
public:
    // LoadLibraryA
    static Module * __stdcall Load(const char * fileName);
    // LoadLibraryExW
    static Module * __stdcall Load(const wchar_t * fileName,
        std::nullptr_t, LoadingFlags flags);
    // FreeLibrary
    bool __stdcall Free();
    // GetProcAddress
    void* __stdcall GetProcAddress(const char * procName);
};

static_assert(sizeof(Module) == 1, \"Not empty!\");
} // namespace winapi
#endif // WINAPI_MODULE_H</cpp>
<p>Common Win32 types such as <tt>UINT</tt> can be easily replaced by standard C++ types, including <tt>BOOL</tt> that can be safely substituted by <tt>bool</tt> since <tt>true</tt> and <tt>false</tt> are guaranted to be 1 and 0 by the standard<note>4.5/4 and 4.7/4</note>.</p>
<p>Note the use of the <tt>winapi</tt> namespace</li>, of function overloading for <tt>Module::Load</tt>, and of <tt>std::nullptr_t</tt> to force the caller to pass <tt>nullptr</tt> (as required by the documentation of <tt>LoadLibraryEx</tt>).</p>
<p>The preprocessor constants expected by <tt>LoadLibraryEx</tt> have been grouped together in a strongly typed enum. This C++11 feature is very convenient to control the underlying type of the enum, but the fact it is strongly typed forbids the values to be combined together as it is normally possible here (it's not always the case). A specific <tt>operator|</tt> had to be added for that purpose.</li>
</ul>
<p>You may wonder he reason why we introduced the <tt>__stdcall</tt> calling convention. That's what we are going to see right now.</p>",

21 => "<h3>Removing the C++ overhead</h3>
<p>One of the most tedious things when writing a wrapper is to declare plenty of functions that do not much more than forwarding their call to the underlying library. It's particularly true in our case since we don't intend to wrap the C functions but to directly call them from C++.</p>
<p>We can greatly reduce the amount of work needed by combining some compiler extension with a pinch of assembly:</p>
<cpp>#include \"module.h\"

#define WIN32_LEAN_AND_MEAN
#include <windows.h>

namespace winapi {

__declspec(naked) Module * __stdcall
Module::Load(const char *) {
    __asm jmp LoadLibraryA
}
__declspec(naked) Module * __stdcall
Module::Load(const wchar_t *, std::nullptr_t, LoadingFlags) {
    __asm jmp LoadLibraryExW
}
__declspec(naked) bool __stdcall
Module::Free() {
    __asm jmp FreeLibrary
}
// handle name collision with Win32
static auto Win32GetProcAddress = &::GetProcAddress;
__declspec(naked) void * __stdcall
Module::GetProcAddress(const char *) {
    __asm jmp dword ptr [Win32GetProcAddress]
}

} // namespace winapi</cpp>
",

23 => "<p>The <tt>__declspec(naked)</tt> is a VC++ specific extension that tells the compiler not to generate prologue and epilogue for the function. The given stack frame is therefore not modified by C++ and it can be directly forwarded to the Win32 function. No more need to push again on the stack the given parameters, neither do we need their names! However we still need to include <i>windows.h</i> in the implementation file in order to get the name of the Win32 functions to jump to.</p>
<p>As you may noticed, we had to declare a global variable <tt>Win32GetProcAddress</tt> to manage the name clash between Win32 and our library. Without that, the <tt>GetProcAddress</tt> member function would jump on itself, creating an infinite loop. The final result works well with Win32 but can't be used with Win64<note>The x64 compiler does not support inline assembly.</note>.</p>
<p>Of course, to work properly, our C++ function prototype must be compatible with the Win32 function being jumped to: same number of arguments, in the same order, same calling convention (<tt>__stdcall</tt>).</p>
<p>If you are familliar with the <abbr title=\"Portable Executable\">PE</abbr> format this trampoline technique should remind you how function import works. In short, for each imported function, the linker creates an entry in a place called the Import Address Table where the actual address of the function in memory is written / updated at runtime<note>Typically by the system loader of Windows.</note>. This IAT entry is in fact a simple pointer to a function living in another module (DLL).</p>
<p>Now, a function pointer is a data, and you don't call a data: you call the function pointed by this data. So our direct call to <tt>LoadLibraryA</tt> should be nonsense:</p>
<pre>call LoadLibraryA</pre>
<p>but it does work fine. That's because the linker generated an additional stub that looks like this:</p>
",

24 => "<pre>LoadLibraryA:
    jmp dword ptr [__imp__LoadLibraryA@8]</pre>
<p>where <tt>__imp__LoadLibraryA@8</tt> is the symbol name for the function pointer in the IAT. Therefore <tt>LoadLibraryA</tt> is not the real function living in kernel32.dll but a local function specific to our module<note>This stub is not generated when our own module importing <tt>LoadLibraryA</tt> is being built but when the module that exports the function (kernel32.dll) was created. It was then placed with other similar stubs in the resulting import library file (kernel32.lib).</note>! As a result, we have our own generated trampoline jumping to another trampoline generated by the linker. Let's fix that now.</p>
<cpp>__declspec(naked) Module * __stdcall
Module::Load(const char *, std::nullptr_t, LoadingFlags) {
    __asm jmp dword ptr [__imp__LoadLibraryA@8]
}
__declspec(naked) Module * __stdcall
Module::Load(const wchar_t *, std::nullptr_t, LoadingFlags) {
    __asm jmp dword ptr [__imp__LoadLibraryExW@8]
}
__declspec(naked) bool __stdcall
Module::Free() {
    __asm jmp dword ptr [__imp__FreeLibrary@4]
}
__declspec(naked) bool __stdcall
Module::GetProcAddress(const char *) {
    __asm jmp dword ptr [__imp__GetProcAddress@8]
}</cpp>
<p>The overhead of our interface is now being reduced to a single jump instruction. In term of runtime overhead, we won't do better. But we can still go further by letting the linker generate the trampoline stub, allowing us to completely remove the implementation code!</p>
<p class=\"important\">This is the final stage of our technical exploration, and the proposed solution works on both x86 and x64 platforms.</p>
",

25 => "<h3>Removing the implementation code</h3>
<p>To get rid of the implementation code, we are going to take advantage of a specific feature of the PE format: export forwarding. It is indeed possible to forward the implementation of an exported function to another DLL. You can see this feature as an aliasing mechanism where one DLL can propose an alternative name for a function exported by another. And that's exactly what we need to create a second interface to Win32!</p>
<p>There are several ways to instruct the linker to create forwarded exports. It can be done via a specific pragma<note>#pragma comment(linker, \"/export:&lt;alias&gt;=&lt;function&gt;\")</note>, directly from the command line, or via a definition file. We will use the .def file so we can remove the .cpp file.</p>
<p>The general syntax is as follows:</p>
<pre>EXPORTS
    &lt;alias&gt;=&lt;function&gt</pre>
<p>where <i>alias</i> is the name of our C++ alias and <i>function</i> the name of the already existing function. Since the forwarding mechanism jumps from the export table to the import table of the module, it is not possible to create an alias of an exported function living in the same module: <i>function</i> must reference a function exported by another module.</p>
<pre>; Win32/x86 implementation
EXPORTS
?Load@Module@winapi@@SGPAV12@PBD@Z=_LoadLibraryA@4
?Free@Module@winapi@@QAG_NXZ=_FreeLibrary@4
?GetProcAddress@Module@winapi@@QAGPAXPBD@Z=_GetProcAddress@8</pre>
",

26 => "<p>The tricky part is to get the mangled names of the symbols:
<ul>
<li>for the C++ symbols: just build some testing code that references it and extract the mangled name from the resulting link error messages</li>
<li>for the Win32 functions: the mangling scheme used is fairly simple: an underscore is prepended to the function name, and an at sign is appended followed by the total size in bytes of the expected parameters</li>
<li>for the Win64 functions: names are not decorated!</li>
</ul>
<pre>; Win64/x64 implementation
EXPORTS
?Load@Module@winapi@@SAPEAV12@PEBD@Z=LoadLibraryA
?Free@Module@winapi@@QEAA_NXZ=FreeLibrary
?GetProcAddress@Module@winapi@@QEAAPEAXPEBD@Z=GetProcAddress</pre>

<p>It's time to “build” the final result. From the x86 command prompt:</p>
<pre>link /DLL /MACHINE:X86 /DEF:module-x86.def /OUT:win32cpp.dll
/NOENTRY kernel32.lib /NOLOGO</pre>
<p>The <tt>/NOENTRY</tt> option creates a resource-only DLL, making it void of any executable code. The resulting file size is 2.5 Ko!</p>
<p>If we supply the <tt>/DEBUG</tt> option, we can afterwards verify that all its exports are forwarded:</p>
<pre>link /dump /exports win32cpp.dll /NOLOGO

ordinal hint RVA      name
  1  0 00001000 ?Free@Module@winapi@@QAG_NXZ = _FreeLibrary@4
  2  1 00001006 ?GetProcAddress@Module@winapi@@QAGPAXPBD@Z =
_GetProcAddress@8
  3  2 0000100C ?Load@Module@winapi@@SGPAV12@PBD@Z =
_LoadLibraryA@4
</pre>
",
/*
27 => "<p>The consequence of that new design is we can no longer provide a static library. Our final result will be a DLL and that's pretty good because that DLL will form the C++ avatar of the existing ones.</p>
<p>Therefore, we need to update our C++ header files in order to tag each class or free function as <tt>__declspec(dllimport)</tt>. Although not mandatory to work, we saw earlier this helps the compiler to optimize the calls to our symbols.</p>

<p>ON PEUT GARDER __stdcall?
The only difference is the calling convention. On x86, it's different from the default C++ one <tt>__cdecl</tt> so it needs to be specified. On x64 there is only one calling convention so there's nothing to do.</p>
",

28 => "<p>Finally, the testing program <i>main.cpp</i>:</p>
<cpp>#include <msgbox.h>
#include <iostream>

using namespace winapi;

int main() {
    MsgBox(nullptr, \"Hello from C++!\", \"ANSI MsgBox\", MB::OK);
    MsgBox(nullptr, L\"Yes or No?\", L\"WIDE MsgBox\",
        MB::YESNO | MB::ICONQUESTION) == ID::YES ?
        std::cout << \"YES!\\n\" :
        std::cout << \"NO!\\n\";
}</cpp>",


30 => "<h2>Part II – Principles in action</h2>
<blockquote>
<p class=\"quote\">Every block of stone has a statue inside it, and it is the task of the sculptor to discover it.</p>
<footer>- Michelangelo</footer>
</blockquote>
<p>Win32 objects generally fall in one of the three main categories: user, GDI and kernel<note>More info <a href=\"http://msdn.microsoft.com/en-us/library/windows/desktop/ms724515(v=vs.85).aspx\">here</a>. There are other libraries such as Wininet that introduce more object and handle types, but we won't cover them.</note>. Basically it means we have three different groups of object handles with different uses and properties. Each group is managed by a specific dll (user32.dll, gdi32.dll, kernel32.dll) via a specific handle map (kernel objects are unkown from the GDI and vice versa). These are three different worlds that we will treat separately.</p>
<p>There are quite a few object types in total so we will restrict our exploration to a significant subset. Similarly, we won't mention each available function for each object but only the most significant ones. When a function dealing with text supports ANSI and WIDE strings, we will only consider the ANSI version. The idea in this second part is to cover the main cases so it becomes easy afterwards to generalize the work to the entire library.</p>
<p>So let's start with the first group.</p>

<h3>User objects</h3>
<p>User objects are related to window management. The Window object is the principal object of that group, and we will only focus on this one. Its corresponding handle type is HWND.</p>
<p>Outlining the boundaries of the Window class is fairly easy: just browse the <a href=\"http://msdn.microsoft.com/en-us/library/windows/desktop/ff468919(v=vs.85).aspx\">window function list</a> and identify the functions that accept a HWND as their first parameter. Functions that return a HWND are good candidates to be static members.</p>",
31 => "<cpp>enum class SW;

class Window : public WinObject {
public:
    static Window * Create();
    static Window * Find();

    bool Destroy();
    bool Move();
    bool Show(SW);
};</cpp>

<p>Here is a simple code to test it:</p>

<cpp>int main() {
    const char * text = \"Hello C++ World!\";

    Window * staticWnd = Window::Create(\"STATIC\", text, );
    staticWnd->Show();

    Window * wnd = Window::Find(\"STATIC\", text);
    if (wnd) {
        wnd->SetText(\"Modified text!\");
    }

    array<char, 40> text;
    if (staticWnd->GetText(text, text.ize())) {
        cout << &text[0];
    }
}</cpp>",

32 => "<p>May be you are asking yourself: is it possible to create a custom window class that inherits from <tt>Window</tt>?</p>
<p>The answer is no. Don't forget we are not trying to create a new C++ library that simplifies the use of Win32, we are trying to expose in C++ how Win32 is working. And as show by the above example, window controls such as the <tt>STATIC</tt> one are <i>not</i> a kind of window: they <i>are</i> of <tt>Window</tt> type.</p>
<p>Allowing to inherit from <tt>Window</tt> would mean Win32 would need to instanciate some user defined types. That's not how it was designed, and it's probably a good thing. The way to go is to create a custom WndClass as shown in the example in Appendice.</p>
<p>We can now see that most Win32 C++ wrappers are actually misleading regarding the true nature of Win32 windows. The message and window procedure system is a mechanism to dynamically attach new member functions or redefine the behavior of existing ones. The message map is actually an equivalent to the virtual pointer table of a C++ class, except it can be dynamically extended and modified.</p>
<p>However, there is a true descendant of <tt>Window</tt>, and it is the <tt>Dialog</tt> class:</p>
<cpp>class Dialog: public Window {
public:
    Window * GetItem(int id);
};</cpp>
<p>Although dialogs are manipulated as regular windows via the HWND handle type, they are not of that type. And <a href=\"http://msdn.microsoft.com/en-us/library/windows/desktop/ms645481(v=vs.85).aspx\">dialog functions</a> refuse any HWND that is not a dialog handle. On the other side, dialog handles are accepted by the regular window funtions.</p>
<p><tt>Dialog</tt> is hence a child of <tt>Window</tt> and we are seeing now how a C++ interface can help to write safer code.</p>",

33 => "<cpp>int main() {
    Dialog * dlg = Dialog::Create();
    dlg->GetItem();
}</cpp>
<p>It is simply not possible to accidently pass a <tt>Window*</tt> to a dialog specific function!</p>
",

34 => "<h3>GDI objects</h3>
<p>Graphical Device Interface objects are related to graphics and painting (on windows, printers, bitmaps...). Unlike kernel object that we will cover later, most GDI objects have their own handle type. Therefore discovering they natural hierarchy is pretty straightforward. And using them in C++ becomes very natural.</p>
<p>We will start by exposing the GDIObj (HGDIOBJ) type which is the base type of many GDI objects:</p>
<cpp>enum class OBJ : std::uint32_t;
enum class STOCK : int;

class GDIObj : public WinObject {
public:
    static GDIObj * GetStock(STOCKOBJ);

    bool Delete();
    OBJ GetType();
};</cpp>
<p>This object is well hidden because it has very few functions that are categorized in the <a href=\"http://msdn.microsoft.com/en-us/library/windows/desktop/dd183539(v=vs.85).aspx\">device context functions</a>. We are going to see how a C++ class hierarchy helps to clarify that point.</p>
<cpp>assert(GDIObj::GetStock(STOCK::BLACK_BRUSH)
    ->GetType() == OBJ::BRUSH);
assert(GDIObj::GetStock(STOCK::BLACK_PEN)
    ->GetType() == OBJ::PEN);
assert(GDIObj::GetStock(STOCK::ANSI_FIXED_FONT)
    ->GetType() == OBJ::FONT);
assert(GDIObj::GetStock(STOCK::DEFAULT_PALETTE)
    ->GetType() == OBJ::PALETTE);</cpp>",

35 => "<p>Now let's have a look to the DeviceContext object:</p>
<cpp>enum class TA : std::uint32_t;

class DeviceContext : public WinObject {
public:
    static DeviceContext * Get(Window*);

    void Release(Window*);
    Window * GetWindow();

    void SelectObject(GDIObj*);
    GDIObj * GetCurrentObject(OBJ);

    ColorRef SetBrushColor(ColorRef);
    ColorRef SetPenColor(ColorRef);

    ColorRef GetTextColor();
    ColorRef SetTextColor(ColorRef);

    uint32_t SetTextAlign(TA);

    bool TextOut(int x, int y, const char * text, int count);
    DrawText();

    int FillRect(const Rect & rect, Brush * brush);
};</cpp>

<p>Browsing the documentation, we can deduce the following relationship between the various types:</p>",

36 => "<p>Here is a quick implementation of some of them:</p>
<cpp>class Pen : public GDIObj {
public:
    Pen * Create();
};

enum class COLOR : int;
enum class HS : int;

class Brush : public GDIObj {
public:
    Brush * GetSysColor(COLOR index);
    Brush * CreateHatch(HS style, ColorRef color);
    Brush * CreateSolid(ColorRef);
};</cpp>
<p>And an example of how they can be used:</p>
<cpp>void onPain(Window * wnd) {
    DeviceContext * dc = DeviceContext::Get(wnd);

    unique_ptr<Brush, GDIObjDeleter> brush = {  };

    dc->SelectObject(
        Brush::GetSysColor());
    dc->SelectObject(Pen::Create());
    dc->SetTextColor(rgb());
    std::string text = \"Hello from C++!\";
    dc->TextOut(10, 10, text.c_str(), text.size());
    dc->FillRect(Rect(10, 10, 20, 20), Brush::GetSysColor());

    dc->Release(wnd);
}</cpp>
<p>As we can see, there is no need to cast the GDI objects passed to <tt>SelectObject</tt> as it is normally necessary with the C interface.</p>
",

40 => "<h3>Kernel objects</h3>
<p>Last, but not least: objects exposed by the Windows kernel. This is my favorite part as it helps reveal all the beauty of C++.</p>
<p>The particularity of the Win32 kernel API is to use the same <tt>HANDLE</tt> type for all the existing objects. That's a different strategy from the GDI team. It makes sense since overwise many casts would be needed, for example each time we use <tt>CloseHandle</tt>. Naming convention helps distinguish between the various groups of functions (File, Mutex, ) but it's all the responsability of the programmer not to passe a pipe handle to an event function: the compiler won't help him. Let's see how to improve that.</p>
<p>The CloseHandle function is a good starting point: it is a nice example of how polymorphism is used in Win32 as it accepts any kind of kernel object. We will naturally move it in a new common base class:</p>
<cpp>class KernelObject: public WinObject {
public:
    bool Close();
};</cpp>
<p>Carefully browsing the documentation, we can draw the following hierarchy between kernel objects:</p>",

41 => "<p>And straight from the beginning we are facing a big challenge: how to deal with multiple inheritance?</p>
<p>Multiple inheritance is indeed problematic. As we said earlier, the virtual keyword is forbidden for us, not only on member function but also with virtual inheritance. So we can't use the diamond shaped inheritance, neither can we inherit from two classes sharing the same ancestor (KernelObject). The following example shows the problem:</p>
<cpp>#include <iostream>
using namespace std;

class A {
public:
    void f(const char * name) {
        cout << hex << name << \" = 0x\" << (void*)this << endl;
    }
};
class B : public A {};
class C : public A {};
class D : public B, public C {};

int main() {
    D d;
    B * b = &d;
    C * c = &d;

    cout << hex << \"d = 0x\" << &d << endl;
    b->f(\"b\");
    c->f(\"c\");
}</cpp>
<pre>TODO</pre>
<p>The undocumented options <tt>/d1reportSingleClassLayout</tt> and <tt>/d1reportAllClassLayout</tt> can help to see what's going on. For example: <tt>/d1reportSingleClassLayoutA /d1reportSingleClassLayoutB</tt>.</p>",

42 => "<p>As we can see, the <tt>this</tt> is not the same among the various types supported by D because D is actually made of two distinct instances of A. Therefore they must have two distinct addresses, even if all our classes are empty. As a result, the value of <tt>this</tt> can be modified (incremented by one) which in turn change the value of the handle passed to the library, making it become invalid.</p>
<p>So, what to do?</p>
<p>An elegant workaround is to redesign our inheritance to be mixin based. It is actually quite difficult to draw in an UML fashion, so I'll directly present the resulting code. The rework happens on SyncronizableObject that becomes a template class:</p>
<cpp>class File : public KernelObject {
public:
    static File * GetStd();

    bool Read();
};

template<typename T>
class SyncronizableObject : public T {
public:
    bool Wait(); // -> WaitForSingleObject
};

class Mutex : SyncronizableObject<KernelObject> {
public:
    static Mutex * Create();
};

class ConsoleInput : SyncronizableObject<File> {
public:
};</cpp>",

43 => "<p>Now, let's see and example of use:</p>
<cpp>int main() {
    ConsoleInput * input = static_cast<ConsoleInput*>(
        File::GetStd());
    input->Wait();
    array<char, 40> text;
    input->Read(text);
    cout << &text[0] << endl;
}</cpp>
<p>In my opinion, this mixin approach is a very elegant design that is closer to the reality than a classical diamond shaped inheritance.</p>
<p>There is however a disadvantage (it's hard to win on all counts): there is no unique base class for synchronizable objects, which is a bit annoying for transcripting the WaitForMultipleObjects function because it requires an array of waitable objects. Since we don't have one unique base class for such objects, we can't provide a uniform array of such objects.</p>
<p>An easy workaround would be to allow to pass an array ok KernelObject. But we can do better by pursuing the use of template code. Let take advantage of another new feature of C++11: variadic templates to create an array of heterogeneous waitable objects:</p>
<cpp>template<typename T, Follow...>
class SyncArray {
public:
    enum { size = others.size + 1 }
private:
    T t;
    SyncArray<Follow...> others;
};</cpp>",


44 => "<cpp>template<typename T, Follow...>
SyncArray<T, Follow...> wait_array(T t, ...) {
    return SyncArray<T, Follow...>(t, ...);
}

// utiliser une initializer list?

int main() {
    Mutex * mutex = nullptr;

    auto list = wait_array(mutex, event, input, thread);
    static_assert(is_pod(list));

    WaitForMultipleObjects(list, list.size);

    wait_array(mutex, file); // compilation error
}</cpp>",

50 => "<h2>Conclusion</h2>
<p>During this carving of the C++ face of Win32, I enjoyed to discover that things could be different than what I'm used to decide about them. More specifically, I was surprised by the following findings:</p>
<ul><li>it is possible to inherit from a C++ class whose constructor and destructor are private, and get valid instances of such children classes</li>
<li>it is possible to provide a function implementation without ever declaring that function (export forwarding makes it possible to implement a whole C++ class without declaring it)</li>
<li>it is possible to replace the interface of an existing implementation with a different new one without touching to the implementation</li></ul>
<p>Being trained since ever to think in the top-down way “one interface, several implementations”, I find the last point – which was actually the technical goal of that article - quite interesting.</p>
<p>What is even more interesting is to understand how it has been accomplished. From a higher perspective, what we have done is to replace one kind of polymorphism by another. Let's take the example of the CloseHandle function:</p>
<ul><li>In the C API, it accepts a HANDLE to a kernel object which can be of many different types : file, process, mutex... This is coercion polymorphism in action, as defined by Cardelli and Wegner.</li>
<li>In our C++ interface, the HANDLE parameter is gone, and a class hierarchy has been introduced instead. Thanks to inclusion polymorphism, it is possible to call the base class function Close on any descendant of KernelObject.</li>
</ul>",
51 => "<p>As we can see, we have substituted a C interface based on coercion by a C++ interface based on inclusion. And that's probably the technical teaching of this exploration: an interface can be swapped for another by playing on the different kinds of polymorphism<note>It is quite funny to note that polymorphism can take different forms!</note>.</p>
<p>At least, that's how we have created a new interface out of an existing implementation, in the sense that sculptors like Michelangelo give to create: revealing something that has always been here!</p>",
//<p>Let me clarify that point. The goal of this article was not Win32 programming, but human mind reprogramming. We have trained ourselves to think outside the main tracks, we have followed unfrequented paths. May be, may be, we even have been the firsts to reach some particular places. This is often related as thinking outside the box. I enjoyed popping like a jack in a box. I hope you did!</p>"
60 => "<h3>The Console class</h3>
<p>Consoles I/O are handled by the Client/Server Runtime Subsystem which is running as a SYSTEM process (csrss.exe). It creates and destroy the visible console windows depending on how many processes are actually needing a visible console window, and how they share existing ones (children processes inherit by default the console of their parent).</p>
<p>That means the console window attached to a process is not owned by that process. CSRSS is the owner, and is the one who paints and animates that window based on the console input out output buffers of the attached processes. This is why you can still interract with a console while its attached process is being paused in a debugger<note>That's also why the “backspace bug” in console text management could crash the whole system until Windows XP SP1 (CSRSS is considered a critical component running under the SYSTEM account).</note>.</p>

<cpp>Console * con = Console::Alloc();
con->SetTitle(“Hello Console!”);
Window * win = con->GetWindow();
std::array<40, char> text;
win->GetText(text);
con->Write(“Window title:”);
con->Write(text);</cpp>",

61 => "<p>A voir:<br />
Note that we use the non static member initializer feature of C++11. Combined with an initializer list, it actually frees us from having to provide a constructor.</p>
<p>A confirmer:<br />
Here is a subtle difference between C++11 and C++98. With the latest, the WndClass would not be considered a POD (Plain Old Data) because it has member functions, while with C++11 WndClass is considered a POD the same way WNDCLASS is.</p>
<p>Note that we use another new feature of C++11: strongly typed enums. One of their particularity over old-style enums is they can be forward declared, what we just did here.</p>
<p>The standard says<note>7.2.5</note> the underlying type of old-style enums can be int or unsigned int depending on the implementation (as long as the values of the enum can fit in). Thanks to C++11, it is now possible to control the exact type of our strong typed enums. And we will make it strictly equivalent to the UINT type used in the original C struct.</p>",

62 => "<p>We take care to explicitely define the size of our enum values:</p>
<cpp>#include <cstdint>

enum class ClassStyle : std::uint32_t {
    VREDRAW          = 0x0001,
    HREDRAW          = 0x0002,
    DBLCLKS          = 0x0008,
    OWNDC            = 0x0020,
    CLASSDC          = 0x0040,
    PARENTDC         = 0x0080,
    NOCLOSE          = 0x0200,
    SAVEBITS         = 0x0800,
    BYTEALIGNCLIENT  = 0x1000,
    BYTEALIGNWINDOW  = 0x2000,
    GLOBALCLASS      = 0x4000,
};</cpp>",

65 => "
<p>Usually, a convenient practice consists in directly extending the C structs via a C++ class. For example:</p>
<cpp>class WndClass : public WNDCLASS {
public:
    WndClass() {
    }
    ATOM Register() {
        return ::RegisterClass(this);
    }
};</cpp>
<p>But we don't want to extend the C interface, we seek to replace it. That's why we forbid ourselves to include any Win32 header.</p>
<p>There is therefore not much options: we must write WndClass from scratch. Because we need two versions (ANSI and WIDE), we will of course use templates:</p>
<cpp>namespace winapi {

class Module;
typedef Module Instance;
class Cursor;

enum class ClassStyle : std::uint8_t;

template<typename TCHAR=char>
class WndClass {
public:
  ClassStyle      style;
  WNDPROC   WndProc;
  int       ClsExtra;
  int       WndExtra;
  Instance * Instance;
  Icon *    Icon;
  Cursor *   Cursor;
  Brush *    Background;
  const TCHAR *   MenuName;
  const TCHAR *   ClassName;

  Atom Register();
};

}</cpp>",

66 => "<p>Whenever the function pointer is correctly dereferenced or the trampoline stub is being called depends on the use or not of <tt>__declspec(dllimport)</tt>. If the function is tagged as being imported, the compiler will know it has to dereference a function pointer, and will generate code accordingly:</p>
<cpp>#define WINUSERAPI __desclspec(dllimport)
WINUSERAPI HMODULE WINAPI LoadLibraryA(LPCSTR lpFileName);</cpp>
<p>Otherwise, if the function is declared as a normal local function:</p>
<cpp>HMODULE WINAPI LoadLibraryA(LPCSTR lpFileName);</cpp>
<p>Then the compiler will think <tt>LoadLibraryA</tt> is a local function and generate call to call it directly instead of going through the function pointer in the IAT. Thanksfully, the import lib will make it work fine, but it will be a bit less efficient (extra jump instruction + cache pollution).</p>

<p>That's the difference between the two following assembly lines:</p>
<pre>call dword ptr [FUNC_POINTER]
call FUNCTION</pre>

<p>The first call is correctly dereferencing the IAT entry for <tt>LoadLibraryA</tt>, while the second line is calling a variable. That's non sense... but it does work! That's because the linker generates the following additional stub:</p>


<pre>call dword ptr [__imp__LoadLibraryA@8]
call dword ptr [_LoadLibraryA@8]</pre>


<p> A symbol name is created for that function pointer so it can be referenced: it is of the form <tt>__imp_FUNCNAME</tt> where <tt>FUNCNAME</tt> is the mangled name of the imported function.</p>

 <p>Based on that design, the compiler and the linker generate the code to reference this particular entry in the IAT each time the imported function needs to be referenced.</p>
<p>Some compilers directly call the address stored in the IAT entry while others generate an additionnal thunk table where each imported function gets a <tt>jmp dword ptr [IAT entry]</tt> instruction<note>VC++ 2013 uses the first technique: <tt>call dword ptr ds:[IAT entry]</tt>.</note>. This is how the compiler and linker can generate code for a function that is not available at build time.</p>",

70 => "<p>Let's see how to make a working example that summarize all we have learned. First, the interface header file <i>msgbox.h</i>:</p>
<cpp>#ifndef MSGBOX_H
#define MSGBOX_H

#include <cstdint>

namespace winapi {

class Window;
enum class MB : std::uint32_t;
enum class ID : int;

ID __stdcall MsgBox(Window * owner, const char * text,
    const char * title, MB type);
ID __stdcall MsgBox(Window * owner, const wchar_t * text,
    const wchar_t * title, MB type);</cpp>",

71 => "<cpp>enum class MB : std::uint32_t {
    OK               = 0x00000000L,
    OKCANCEL         = 0x00000001L,
    ABORTRETRYIGNORE = 0x00000002L,
    YESNOCANCEL      = 0x00000003L,
    YESNO            = 0x00000004L,
    RETRYCANCEL      = 0x00000005L,

    ICONHAND         = 0x00000010L,
    ICONQUESTION     = 0x00000020L,
    ICONEXCLAMATION  = 0x00000030L,
    ICONASTERISK     = 0x00000040L,
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
    OK     = 1,
    CANCEL = 2,
    ABORT  = 3,
    RETRY  = 4,
    IGNORE = 5,
    YES    = 6,
    NO     = 7,
};

} // winapi
#endif</cpp>",*/
);

?>
