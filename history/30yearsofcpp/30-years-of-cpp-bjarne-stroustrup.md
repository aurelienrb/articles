Title: Bjarne Stroustrup on the 30th anniversary of Cfront (the first C++ compiler)
Date: 2015, October 14th
![](images/timeline-800.png)
---

## Exactly 30 years ago, CFront 1.0 was released together with the first edition of your book *The C++ Programming Language*. Was it important to have them released on the same day?

I don’t know if it was important, but it seemed a good idea at the time. Both <abbr title="The company selling the compiler">AT&T</abbr> and <abbr title="The publisher of book">Addison-Wesley</abbr> asked me when would be a good release day, so I told them October 14 because I was giving a talk at the <abbr title="Association for Computing Machinery">ACM</abbr> conference and could announce it there.

## That made you twice an author, and probably required twice more work. But did writing a book helped you in designing a better language?

It was a necessary book. The tiny, but growing, community needed it. It wasn’t even my own idea to write it. Al Aho, then my neighbor at Bell Labs, pointed out that need. I completely underestimated the amount of work needed to write The *C++ Programming Language*. Writing clearly on new topic is hard. **I did learn a fair bit about C++ by trying to explain it to people**. C++ was improved to simplify my explanations by making the language more logical or more capable. Writing a tutorial is an effective design technique.

![Photo courtesy of Paul McJones.](images/meet-the-author-1985.jpg)

## Do you remember how you felt on this Monday 14th October 1985, when you gave your workshop “*A C++ tutorial*”?

I don’t remember. In fact, I don’t remember all that much from the 1980s. That was a very busy time for me. From 1979 to 1991 the number of C++ programmers doubled every 7.5 month. Such growth generates a lot of work. Today, the number of C++ users is estimated to 4.4 million ([JetBrains survey](http://blog.jetbrains.com/clion/2015/07/infographics-cpp-facts-before-clion/)). It was a big day for me, but the only thing I remember is getting the first copy of my 1st edition.

## At that time, what was your vision about C++? How did you see its future?

Obviously, I did not predict C++’s enormous, and occasionally explosive growth. I was focused on improving the language, on figuring out how to write libraries, on improving the compiler, and on explaining how to use it all.

On the other hand, **most of what we see in C++ today has deep roots into the early days**. Classes, function declarations (function prototypes), constructors, and destructors were part of the very first design. I included inline functions and overloaded assignments a couple of years later. The distinction between initialization and assignment was there. General overloading of operators came a bit later (1983 or so) together with virtual functions. **People often forget that parameterized types were there from the start**. I used a vector macro with the element type as a macro parameter from the earliest days and Cfront shipped with a <generic.h> header. For a couple of years, I thought macros would be enough to support generic programming. I was very wrong about that, but I was right about the need to parameterize types and functions with (other) types. The result became templates (1988).

The basic idea was to extend the language with facilities allowing users to define powerful, elegant, and efficient abstractions. This contrasts with the idea of supporting application-specific abstractions directly in the language. To this day, C++ has C’s basic machine model to allow efficient and reasonable portable use of hardware. We are also still working on incremental improvements of C++’s support for efficient abstraction.

## Most attempt to create a successful language fail. How was it to realize that more and more software around you were using the language you created? What is, according to you, the main reason of that success?

Gratifying and a bit scary. It is good to have done something that proved useful to many, but it is also a great responsibility – especially as the language evolves. If we add good and useful features to the language, we have done some good for the world, but if we mess up, we can do great harm. So far, I think that the evolution of C++ has been a sequence of improvements. Not every features has been a success, but most have been very helpful to many and the failures have not been fatal. Each year C++ has become a better tool. Today’s C++ is immensely more useful than the 1985 release 1.0. We can write much more elegant code today and it runs faster (even if you take the difference in hardware performance into account).

The reason for the success? There must be many. To succeed, a language must be reasonably good at just about anything its users need and must not fail completely at anything. It is not enough to be best in the world at one or two things. Fundamentally, C++ addressed and still addresses critical issues in systems where performance and hardware access are critical (e.g., “systems programming”), and especially for systems where complexity needs to be managed. Secondly, **C++ was carefully and gradually evolved in response to real-world problems; it grew. It was never an “Ivory tower project”**. Finally, I think it is important that C++ isn’t fueled by hype: I made comparatively modest promises and kept them.

## You spent most of your life working on C++, and you are still very active today. What is your main motivation behind such a dedication? Have you never been tempted to work on another new language?

**I tried to get out of developing C++ a few times, but it always dragged me back**. I feel that working with C++ is my best chance of making a significant contribution. Thus, C++ is my main tool for research and development. The lessons learned – by me and by many others in the C++ community – are then fed back into the language and its libraries where they can benefit millions.

I have of course dreamed of designing a new and better language, but it takes a long time for a language to develop from a set of ideas to a useful tool. It is hard to compete with C++ on its own turf, and my main interests are in areas where C++ is a good tool. Also, most new languages fail. **It is easy to make mistakes when designing from scratch. A successful large system is almost always derived from a smaller working system**.

It is hard to integrate an idea in a large language like C++ with complexities from its long evolution, but once the idea is supported by C++, it can be used by millions rather than the few hundreds who could benefit from the early versions of a new language.

## What has changed in 30 years? What has remained the same?

The machine model and the wish for better support for abstraction are unchanged are as relevant as ever. So is my emphasis on the use of a static (compile-time) type system.

The use of exceptions and templates have radically improved how we can write elegant and efficient code. Both were first mentioned as possible directions for C++ in a paper I wrote for IEEE Software in 1986. Exception together with constructors and destructors gave us resource safety (RAII). Templates allowed Alex Stepanov’s STL and his ideas of generic programming to thrive. Just this year, we got direct language support for [concepts](http://www.open-std.org/jtc1/sc22/wg21/docs/papers/2015/p0121r0.pdf) to complete C++’s template facilities.

**In the late 1980s, people were over-excited about the use of class hierarchies**. I was rather more interested in blending different programming styles into a coherent whole. My 1st edition (deliberately) didn’t even use the term “Object-Oriented Programming” and **I was giving talks with titles such as “C++ is not just an object-oriented programming language”. Later, people got over-excited about the use of templates** in the form of generic programming and template meta-programming, sometimes for getting that simple solution are often the best. I am still looking for ways to articulate my notion of elegant programming based on a synthesis of language features and library facilities.

One very recent thing that I suspect will be very important over the next few years is an effort to develop a set of tool and library coding guidelines. This will make it far easier for the C++ community to effectively use new facilities. One really new aspect of this is that we have tools that can eliminate dangling pointers, opening the possibility of completely type-and resource-safe C++ programs. In particular, we eliminate all resource leaks without using a garbage collector (because we don’t generate any garbage), so we don’t suffer any loss of performance despite the increased safety. [We don’t restrict what C++ can be used for](https://isocpp.org/blog/2015/09/cpp-core-guidelines-bjarne-stroustrup-herb-sutter) either.

I hope that this can help fight **a problem that has plagued C++ forever: poor teaching and poor understanding of C++ even among its practitioners**. There has always been a tendency to describe C++ as some odd variant of something else. For example, C++ is still frequently taught as “a few features added to C” or as “an unsafe Java with a few modern features missing.” This does enormous harm to actual C++ use. My new [A Tour of C++](http://www.amazon.fr/gp/product/0321958314/) might also help. I describes all of C++ and its standard library at a fairly high level in fewer pages than K&R uses for C. It is aimed at people who are already programmers, rather than for complete novices.

## In a few days, the C++ committee will gather in Hawaii to work on the next major version of C++. What do you think C++17 will look like?

I suspect that I’ll have a better idea of what C++17 will be after next week’s standards meeting in Kona [Hawaii]. But I’m (still) and optimist. C++11 was a great improvement over C++98 and I expect C++17 to be an improvement of similar magnitude over C++11.

I hope to see:

* concepts (already an ISO TS) for better definition of templates
* modules for faster compilation
* an improved STL with ranges (proposal by Eric Niebler)
* co-routines (proposal by Gor Nishanov)
* a networking support library (proposal by Chris Kohlhoff)
* better support for concurrent and parallel programming
* and [more](http://www.open-std.org/jtc1/sc22/wg21/docs/papers/2015/n4492.pdf)!
