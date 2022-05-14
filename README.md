`oatc` is a compiler for an experimental programming language called Oat.

_The Oat programming language and the `oatc` compiler are experiments at this
point—they are not usable. The design of the programming language is subject
to frequent change. No backwards compatibility is guaranteed._

# Some History

Back in late 2017, I started working on a C-like programming language. One of
the goals was to prevent integer overflow at compile time—if possible. So one
of the big features that I looked into adding were ranged integer types. That
ended up being a failed experiment; implementing ranged integer types looked
simple on the surface, but once I started looking into it further, it felt like
I would be going down a rabbit hole. This language was called Bever.

Later, I pivoted towards a higher-level direction, and decided to work on a
scripting language. One of the primary goals of this scripting language was
that interaction with C code had to be convenient. Today, that is still a goal.
This language was called Oat, and it remains named that way today. Whether Oat
ends up a scripting language or not is not yet settled; it might become more
low-level with time.

Back when I started with Bever, I had a "Better C" mindset: I wanted to keep
the language as close to C as I could, but add some new features to help
prevent mistakes. Today, making Oat be C-like is not a major concern—although
the desire does creep in from time to time. It took a while to built up the
courage to break away from the "Better C" mindset, but I feel like it opened up
a lot of possibilities. So now I look for influence from many different places,
and I feel like Oat is better off for it.
