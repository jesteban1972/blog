<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Enum/PostDiffusio.php

namespace App\Enum;

/**
 * internal meaning of PostDiffusio enum / diffusio field values meanings.
 */
enum PostDiffusio: int
{
    /**
     * PostDiffusio::PRIVATA
     *
     * this degree of (no)-diffusion is reserved for material that must remain strictly private, with no intention of
     * being evaluated, cited, or distributed. descriptions under this level are visible only to their authors and serve
     * as personal notes, drafts, or reflections. they do not form part of any publication cycle. in spite of that, and
     * although unlikely, private descriptions may eventually evolve into higher diffusion states if the author judges
     * them mature enough to share beyond the strictly private sphere.
     */
    case PRIVATA = 1;      // author only

    /**
     * PostDiffusio::AD_INTERNUM
     *
     * this degree corresponds to material not intended for publication. it applies to content in an early, incomplete,
     * or exploratory stage of elaboration, preserved mainly for internal reference. such descriptions often function as
     * working documents, memoranda, or experimental notes that record the project’s evolving activity. most of the
     * time, they are texts not yet suitable for public release and not expected to be evaluated or commented on
     * externally. PostDiffusio::AD_INTERNUM thus marks the threshold between the purely private sphere and the
     * semi-formal internal record of the project’s intellectual process.
     */
    case AD_INTERNUM = 2;  // internal working docs

    /**
     * PostDiffusio::SUBMITTENDA
     *
     * still restricted material, probably a promotion candidate. this degree designates content that has reached a
     * presentable state and may be shared with a limited audience, typically ROLE_SUPPORTER members within the context
     * of the so-called Registrum Maius. while not yet public, such descriptions are candidates for community attention,
     * comment, or endorsement. PostDiffusio::SUBMITTENDA material often invites discussion, revision, or editorial
     * curation before attaining a higher diffusion level. its purpose is to encourage engagement and assess readiness
     * for elevation to PostDiffusio::ACCEPTATA or even PostDiffusio::PUBLICATA.
     */
    case SUBMITTENDA = 3;  // candidates for promotion (ROLE_SUPPORTER)

    /**
     * PostDiffusio::ACCEPTATA
     *
     * featured, promoted, curated, and accepted material. this degree of diffusion applies to descriptions that have
     * been reviewed, curated, or otherwise endorsed for internal publication. these contents are fully publishable
     * within the community framework and accessible to authenticated participants (ROLE_USER and ROLE_SUPPORTER).
     * they constitute the Registrum Minus, that is, the project’s endorsed Corpus Minor, worthy of citation or
     * reference. promotion to this level acknowledges both the intrinsic merit of the text and its conformity with the
     * community’s thematic focus and editorial standards.
     */
    case ACCEPTATA = 4;    // curated/endorsed (ROLE_USER)

    /**
     * PostDiffusio::PUBLICA
     *
     * public material. descriptions marked with the PostDiffusio::PUBLICA degree are released for open access and
     * demonstration purposes. posts at this level are visible to all visitors, whether authenticated or not, and may
     * represent exemplary, outreach, or pedagogical content. public diffusion indicates that the description can
     * circulate freely beyond the internal community, serving as a public expression of its ideas and methods. it
     * embodies the most extensive and outward-oriented degree of diffusion within the system.
     */
    case PUBLICA = 5;      // open access (Anonymous)

    /**
     * get a readable string label for UI rendering.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::PRIVATA => 'privata',
            self::AD_INTERNUM => 'ad internum',
            self::SUBMITTENDA => 'submittenda',
            self::ACCEPTATA => 'acceptata',
            self::PUBLICA => 'publica',
        };
    }

    /**
     * get the Font Awesome 6 class string corresponding to the diffusion level.
     */
    public function getIconClass(): string
    {
        return match($this) {
            self::PRIVATA => 'fa-solid fa-lock',
            self::AD_INTERNUM => 'fa-solid fa-key',
            self::SUBMITTENDA => 'annales-icon-maius',
            self::ACCEPTATA => 'annales-icon-minus',
            self::PUBLICA => 'fa-solid fa-earth-europe',
        };
    }
}
