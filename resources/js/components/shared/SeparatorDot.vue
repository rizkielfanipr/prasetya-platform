<template>
    <!--
        A drawn middle dot: Overpass's "·" sits off-centre in its advance, so a typed one reads as glued to the next word.
        Screen readers get a comma instead of a symbol.
    -->
    <span class="mx-2 inline-block size-[3px] shrink-0 rounded-full bg-current align-middle opacity-70" aria-hidden="true" /><span class="sr-only">, </span>
</template>
