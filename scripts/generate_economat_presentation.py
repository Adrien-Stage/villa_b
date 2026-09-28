#!/usr/bin/env python3
"""
Générateur de présentation PowerPoint (.pptx) pour le Cas Pratique de Gestion Complète de l'Économat
PMS Hôtellerie & Restauration — Villa B / Kore Teck / Zingana
"""

import os
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE

def build_presentation(output_path: str):
    prs = Presentation()
    # Format 16:9 Widescreen
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    blank_layout = prs.slide_layouts[6]

    # Palette de couleurs professionnelle
    C_NAVY_DARK   = RGBColor(11, 19, 43)     # #0B132B Fond foncé titre
    C_NAVY_LIGHT  = RGBColor(27, 38, 59)     # #1B263B
    C_PRIMARY     = RGBColor(15, 23, 42)     # #0F172A Titres
    C_BG_PAGE     = RGBColor(248, 250, 252)  # #F8FAFC Fond clair
    C_WHITE       = RGBColor(255, 255, 255)  # Blanc pur
    C_CARD_BG     = RGBColor(255, 255, 255)
    C_CARD_BORDER = RGBColor(226, 232, 240)  # #E2E8F0
    C_EMERALD     = RGBColor(16, 185, 129)   # #10B981 Accent vert
    C_EMERALD_BG  = RGBColor(236, 253, 245)  # #ECFDF5
    C_BLUE_ACCENT = RGBColor(14, 165, 233)   # #0EA5E9 Accent bleu
    C_BLUE_DARK   = RGBColor(30, 58, 138)    # #1E3A8A Bleu royal
    C_AMBER       = RGBColor(217, 119, 6)    # #D97706 Litige/Alerte
    C_AMBER_BG    = RGBColor(254, 243, 199)  # #FEF3C7
    C_PURPLE      = RGBColor(124, 58, 237)   # #7C3AED Signature
    C_TEXT_MAIN   = RGBColor(15, 23, 42)     # #0F172A
    C_TEXT_MUTED  = RGBColor(100, 116, 139)  # #64748B
    C_TEXT_LIGHT  = RGBColor(203, 213, 225)  # #CBD5E1

    def set_slide_background(slide, color):
        bg = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, Inches(13.333), Inches(7.5))
        bg.fill.solid()
        bg.fill.fore_color.rgb = color
        bg.line.fill.background()
        return bg

    def add_header(slide, chip_text, title_text, subtitle_text, dark=False):
        # Chip / Badge
        chip = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(0.8), Inches(0.4), Inches(4.5), Inches(0.35))
        chip.fill.solid()
        chip.fill.fore_color.rgb = C_BLUE_ACCENT if dark else C_BLUE_DARK
        chip.line.fill.background()
        tf_chip = chip.text_frame
        tf_chip.vertical_anchor = MSO_ANCHOR.MIDDLE
        p_chip = tf_chip.paragraphs[0]
        p_chip.text = chip_text
        p_chip.font.name = "Segoe UI"
        p_chip.font.size = Pt(10)
        p_chip.font.bold = True
        p_chip.font.color.rgb = C_WHITE
        p_chip.alignment = PP_ALIGN.CENTER

        # Titre
        tx_box = slide.shapes.add_textbox(Inches(0.8), Inches(0.78), Inches(11.7), Inches(0.55))
        tf = tx_box.text_frame
        tf.word_wrap = True
        tf.margin_left = tf.margin_top = tf.margin_right = tf.margin_bottom = 0
        p = tf.paragraphs[0]
        p.text = title_text
        p.font.name = "Segoe UI"
        p.font.size = Pt(22)
        p.font.bold = True
        p.font.color.rgb = C_WHITE if dark else C_PRIMARY

        # Sous-titre
        tx_box_sub = slide.shapes.add_textbox(Inches(0.8), Inches(1.35), Inches(11.7), Inches(0.38))
        tf_sub = tx_box_sub.text_frame
        tf_sub.word_wrap = True
        tf_sub.margin_left = tf_sub.margin_top = tf_sub.margin_right = tf_sub.margin_bottom = 0
        p_sub = tf_sub.paragraphs[0]
        p_sub.text = subtitle_text
        p_sub.font.name = "Segoe UI"
        p_sub.font.size = Pt(12)
        p_sub.font.color.rgb = C_TEXT_LIGHT if dark else C_TEXT_MUTED

        # Ligne de séparation
        line = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.8), Inches(1.78), Inches(11.733), Inches(0.02))
        line.fill.solid()
        line.fill.fore_color.rgb = RGBColor(51, 65, 85) if dark else C_CARD_BORDER
        line.line.fill.background()

    def add_card(slide, left, top, width, height, bg_color=C_WHITE, border_color=C_CARD_BORDER):
        card = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, left, top, width, height)
        card.fill.solid()
        card.fill.fore_color.rgb = bg_color
        card.line.color.rgb = border_color
        card.line.width = Pt(1)
        return card

    # =========================================================================
    # SLIDE 1 : PAGE DE GARDE (Dark Theme)
    # =========================================================================
    slide1 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide1, C_NAVY_DARK)

    # Décoration bandeau gauche
    band = slide1.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0), Inches(0), Inches(0.35), Inches(7.5))
    band.fill.solid()
    band.fill.fore_color.rgb = C_EMERALD
    band.line.fill.background()

    # Badge Catégorie
    badge1 = slide1.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, Inches(1.2), Inches(0.9), Inches(5.8), Inches(0.45))
    badge1.fill.solid()
    badge1.fill.fore_color.rgb = RGBColor(19, 78, 74)
    badge1.line.color.rgb = C_EMERALD
    badge1.line.width = Pt(1.5)
    tf_b1 = badge1.text_frame
    tf_b1.vertical_anchor = MSO_ANCHOR.MIDDLE
    pb1 = tf_b1.paragraphs[0]
    pb1.text = "PMS HÔTELLERIE & RESTAURATION — VILLA B / KORE TECK / ZINGANA"
    pb1.font.name = "Segoe UI"
    pb1.font.size = Pt(10.5)
    pb1.font.bold = True
    pb1.font.color.rgb = C_EMERALD
    pb1.alignment = PP_ALIGN.CENTER

    # Titre Principal
    tb_title = slide1.shapes.add_textbox(Inches(1.2), Inches(1.55), Inches(11.0), Inches(1.8))
    tf_t = tb_title.text_frame
    tf_t.word_wrap = True
    tf_t.margin_left = tf_t.margin_top = tf_t.margin_right = tf_t.margin_bottom = 0

    pt1 = tf_t.paragraphs[0]
    pt1.text = "CAS PRATIQUE D'ÉVALUATION & DE RECETTE"
    pt1.font.name = "Segoe UI"
    pt1.font.size = Pt(32)
    pt1.font.bold = True
    pt1.font.color.rgb = C_WHITE

    pt2 = tf_t.add_paragraph()
    pt2.text = "Gestion Intégrale de l'Économat, des Stocks & Flux Matières"
    pt2.font.name = "Segoe UI"
    pt2.font.size = Pt(22)
    pt2.font.bold = True
    pt2.font.color.rgb = C_BLUE_ACCENT
    pt2.space_before = Pt(8)

    # Description
    tb_desc = slide1.shapes.add_textbox(Inches(1.2), Inches(3.5), Inches(11.0), Inches(1.2))
    tf_d = tb_desc.text_frame
    tf_d.word_wrap = True
    tf_d.margin_left = tf_d.margin_top = tf_d.margin_right = tf_d.margin_bottom = 0
    pd = tf_d.paragraphs[0]
    pd.text = (
        "Guide d'exécution opérationnel pour tester et valider de bout en bout l'ensemble des modules développés :\n"
        "• Référentiels & Catalogues Fournisseurs • Bons de Réquisition Inter-Services signés numériquement\n"
        "• Bons de Commande Fournisseur chiffrés • Bons d'Entrée contradictoires avec gestion des litiges\n"
        "• Mouvements de stock & valorisation PUMP • Inventaires physiques périodiques • Exports & Traçabilité."
    )
    pd.font.name = "Segoe UI"
    pd.font.size = Pt(13.5)
    pd.font.color.rgb = C_TEXT_LIGHT

    # 3 Cartes d'infos métadonnées en bas
    cards_data = [
        ("CIBLE DE RECETTE", "Économe, Contrôleur de gestion, Direction d'exploitation, Chefs de service", C_EMERALD),
        ("ENVIRONNEMENT TEST", "Application Web Laravel 11 / MariaDB / Docker multi-instances", C_BLUE_ACCENT),
        ("CONFORMITÉ & SÉCURITÉ", "Calculs stricts en centimes FCFA, Signatures Qwigley, RBAC vérifié", C_PURPLE),
    ]
    card_w = Inches(3.45)
    for i, (head, val, color_bar) in enumerate(cards_data):
        c_left = Inches(1.2) + i * Inches(3.75)
        cd = add_card(slide1, c_left, Inches(5.1), card_w, Inches(1.6), bg_color=C_NAVY_LIGHT, border_color=RGBColor(51, 65, 85))
        
        # Mini barre accent
        m_bar = slide1.shapes.add_shape(MSO_SHAPE.RECTANGLE, c_left, Inches(5.1), card_w, Inches(0.06))
        m_bar.fill.solid()
        m_bar.fill.fore_color.rgb = color_bar
        m_bar.line.fill.background()

        tb_c = slide1.shapes.add_textbox(c_left + Inches(0.2), Inches(5.28), card_w - Inches(0.4), Inches(1.2))
        tfc = tb_c.text_frame
        tfc.word_wrap = True
        pc1 = tfc.paragraphs[0]
        pc1.text = head
        pc1.font.name = "Segoe UI"
        pc1.font.size = Pt(9.5)
        pc1.font.bold = True
        pc1.font.color.rgb = color_bar

        pc2 = tfc.add_paragraph()
        pc2.text = val
        pc2.font.name = "Segoe UI"
        pc2.font.size = Pt(11)
        pc2.font.color.rgb = C_WHITE
        pc2.space_before = Pt(4)

    # =========================================================================
    # SLIDE 2 : VUE D'ENSEMBLE DU WORKFLOW (6 JALONS)
    # =========================================================================
    slide2 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide2, C_BG_PAGE)
    add_header(slide2, "CARTOGRAPHIE FONCTIONNELLE", "Vue d'Ensemble du Cycle Opérationnel de l'Économat",
               "Les 6 étapes interconnectées du flux matière avec traçabilité et validation contradictoire")

    steps = [
        ("JALON 1", "RÉFÉRENTIELS & FOURNISSEURS", "Paramétrage des partenaires, catalogue articles, prix négociés en FCFA et unités de mesure.", C_BLUE_DARK),
        ("JALON 2", "BONS DE RÉQUISITION", "Expression des besoins par les services (Housekeeping, F&B...). Signature cursive Qwigley.", C_PURPLE),
        ("JALON 3", "BONS DE COMMANDE (PO)", "Émission par l'Économe d'un bon filtré sur un fournisseur unique avec engagement chiffré.", C_BLUE_DARK),
        ("JALON 4", "BONS D'ENTRÉE (BR)", "Réception contradictoire à quai (reçu, accepté, rejeté), motif de litige et visa Économe.", C_AMBER),
        ("JALON 5", "MOUVEMENTS & PUMP", "Intégration automatique en stock, valorisation PUMP et sortie sur réquisition validée.", C_EMERALD),
        ("JALON 6", "INVENTAIRES PHYSIQUES", "Campagne de comptage, calcul automatique des écarts en volume/valeur et clôture immuable.", C_NAVY_LIGHT),
    ]

    col_w = Inches(3.7)
    row_h = Inches(2.25)
    coords = [
        (Inches(0.8), Inches(2.0)),
        (Inches(4.8), Inches(2.0)),
        (Inches(8.8), Inches(2.0)),
        (Inches(0.8), Inches(4.55)),
        (Inches(4.8), Inches(4.55)),
        (Inches(8.8), Inches(4.55)),
    ]

    for i, (num, title, desc, acc_color) in enumerate(steps):
        x, y = coords[i]
        add_card(slide2, x, y, col_w, row_h)

        # Header tag shape inside card
        tag = slide2.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, x + Inches(0.2), y + Inches(0.2), Inches(1.1), Inches(0.32))
        tag.fill.solid()
        tag.fill.fore_color.rgb = acc_color
        tag.line.fill.background()
        t_tag = tag.text_frame
        t_tag.vertical_anchor = MSO_ANCHOR.MIDDLE
        pt = t_tag.paragraphs[0]
        pt.text = num
        pt.font.name = "Segoe UI"
        pt.font.size = Pt(9.5)
        pt.font.bold = True
        pt.font.color.rgb = C_WHITE
        pt.alignment = PP_ALIGN.CENTER

        # Content text
        tb = slide2.shapes.add_textbox(x + Inches(0.2), y + Inches(0.65), col_w - Inches(0.4), Inches(1.4))
        tf = tb.text_frame
        tf.word_wrap = True
        p1 = tf.paragraphs[0]
        p1.text = title
        p1.font.name = "Segoe UI"
        p1.font.size = Pt(12.5)
        p1.font.bold = True
        p1.font.color.rgb = C_PRIMARY

        p2 = tf.add_paragraph()
        p2.text = desc
        p2.font.name = "Segoe UI"
        p2.font.size = Pt(11)
        p2.font.color.rgb = C_TEXT_MUTED
        p2.space_before = Pt(4)

    # Bottom bar alert
    bar = add_card(slide2, Inches(0.8), Inches(6.9), Inches(11.733), Inches(0.45), bg_color=C_EMERALD_BG, border_color=C_EMERALD)
    tf_bar = bar.text_frame
    tf_bar.vertical_anchor = MSO_ANCHOR.MIDDLE
    p_bar = tf_bar.paragraphs[0]
    p_bar.text = "  ✔ RÈGLE D'OR MÉTIER : Zéro calcul flottant (montants en centimes FCFA), traçabilité 100% auditable et respect absolu de la matrice RBAC."
    p_bar.font.name = "Segoe UI"
    p_bar.font.size = Pt(10)
    p_bar.font.bold = True
    p_bar.font.color.rgb = RGBColor(6, 95, 70)

    # =========================================================================
    # SLIDE 3 : JALON 1 — PARAMÉTRAGE RÉFÉRENTIELS & CATALOGUES
    # =========================================================================
    slide3 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide3, C_BG_PAGE)
    add_header(slide3, "JALON 1 — TEST OPÉRATIONNEL", "Paramétrage des Référentiels & Catalogues Fournisseurs",
               "Valider l'association stricte Fournisseur <-> Produits avec prix unitaires et unités contractuels")

    # Carte Gauche : Données de test
    add_card(slide3, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide3.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. JEU DE DONNÉES DE TEST"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    items = [
        ("Fournisseur 1 : Prodimex Hygiène SARL", [
            "• Article A : Savonnette d'accueil 30g — Unité : Pièce — Prix : 150 FCFA (Seuil min : 30)",
            "• Article B : Détergent Sol Professionnel 5L — Unité : Bidon — Prix : 4 500 FCFA (Seuil min : 10)",
        ]),
        ("Fournisseur 2 : Société des Brasseries (SABC)", [
            "• Article C : Eau Minérale 1.5L (Pack x6) — Unité : Pack — Prix : 2 400 FCFA (Seuil min : 15)",
            "• Article D : Jus d'Orange Naturel 1L — Unité : Bouteille — Prix : 1 200 FCFA",
        ]),
        ("Fournisseur 3 : BuroTech Équipements", [
            "• Article E : Rame Papier A4 80g — Unité : Rame — Prix : 3 200 FCFA (Seuil min : 5)",
        ]),
    ]

    for f_title, sub_items in items:
        p_ft = tf_l.add_paragraph()
        p_ft.text = f_title
        p_ft.font.name = "Segoe UI"
        p_ft.font.size = Pt(11)
        p_ft.font.bold = True
        p_ft.font.color.rgb = C_PRIMARY
        p_ft.space_before = Pt(8)
        for s in sub_items:
            ps = tf_l.add_paragraph()
            ps.text = s
            ps.font.name = "Segoe UI"
            ps.font.size = Pt(10)
            ps.font.color.rgb = C_TEXT_MUTED
            ps.space_before = Pt(2)

    # Carte Droite : Déroulé de test & Critères de succès
    add_card(slide3, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide3.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. DÉROULÉ DU TEST PAS-À-PAS"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_EMERALD

    test_steps = [
        "Étape 1 : Accéder au module 'Paramètres > Fournisseurs' et créer les 3 fournisseurs avec coordonnées complètes.",
        "Étape 2 : Créer les articles dans 'Économat > Articles' avec leurs catégories (Hygiène, Boissons, Bureautique).",
        "Étape 3 : Associer chaque article à son fournisseur attitré en spécifiant le prix unitaire d'achat et l'unité de mesure.",
        "Étape 4 : Définir les seuils de stock d'alerte et de sécurité sur chaque fiche produit.",
    ]
    for s in test_steps:
        p_s = tf_r.add_paragraph()
        p_s.text = s
        p_s.font.name = "Segoe UI"
        p_s.font.size = Pt(10.5)
        p_s.font.color.rgb = C_TEXT_MAIN
        p_s.space_before = Pt(6)

    p_crit = tf_r.add_paragraph()
    p_crit.text = "3. CRITÈRES DE SUCCÈS"
    p_crit.font.name = "Segoe UI"
    p_crit.font.size = Pt(12)
    p_crit.font.bold = True
    p_crit.font.color.rgb = C_PRIMARY
    p_crit.space_before = Pt(12)

    crit_items = [
        "✔ Liaison exclusive vérifiée : un article fournisseur n'apparaît que pour son partenaire.",
        "✔ Les prix négociés sont préremplis automatiquement sans possibilité de saisie arbitraire erronée.",
    ]
    for c in crit_items:
        p_c = tf_r.add_paragraph()
        p_c.text = c
        p_c.font.name = "Segoe UI"
        p_c.font.size = Pt(10)
        p_c.font.bold = True
        p_c.font.color.rgb = RGBColor(6, 95, 70)
        p_c.space_before = Pt(3)

    # Bottom helper
    add_card(slide3, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_WHITE, border_color=C_CARD_BORDER)
    tb_b = slide3.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "Point d'audit : Contrôler dans la base de données la table 'suppliers' et la table pivot 'products' / 'supplier_product'."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(9.5)
    pb.font.color.rgb = C_TEXT_MUTED

    # =========================================================================
    # SLIDE 4 : JALON 2 — BONS DE RÉQUISITION INTER-SERVICES (Signature Qwigley)
    # =========================================================================
    slide4 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide4, C_BG_PAGE)
    add_header(slide4, "JALON 2 — TEST OPÉRATIONNEL", "Expression des Besoins : Bons de Réquisition Internes",
               "Tous les services (Housekeeping, F&B, Compta...) adressent des demandes d'approvisionnement signées")

    # Carte Gauche : Scénario d'émission
    add_card(slide4, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide4.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. SCÉNARIOS D'ÉMISSION PAR SERVICE"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_PURPLE

    scenarios = [
        ("Service Housekeeping (Gouvernante)", "Bon N° REQ-HK-001", [
            "• 20 Savonnettes d'accueil 30g (Chambres VIP Étage 2)",
            "• 3 Bidons de Détergent Sol 5L (Entretien courant)",
        ]),
        ("Service Restauration / Bar (Maître d'hôtel)", "Bon N° REQ-RESTO-002", [
            "• 8 Packs Eau Minérale 1.5L (Service séminaire)",
            "• 4 Bouteilles Jus d'Orange 1L (Petit déjeuner)",
        ]),
        ("Service Comptabilité (Responsable Financier)", "Bon N° REQ-COMPTA-003", [
            "• 2 Rames Papier A4 80g (Clôture mensuelle & facturation)",
        ]),
    ]

    for s_srv, s_code, lines in scenarios:
        ps = tf_l.add_paragraph()
        ps.text = f"{s_srv} — {s_code}"
        ps.font.name = "Segoe UI"
        ps.font.size = Pt(11)
        ps.font.bold = True
        ps.font.color.rgb = C_PRIMARY
        ps.space_before = Pt(6)
        for ln in lines:
            pl = tf_l.add_paragraph()
            pl.text = ln
            pl.font.name = "Segoe UI"
            pl.font.size = Pt(10)
            pl.font.color.rgb = C_TEXT_MUTED

    # Focus Signature Qwigley
    add_card(slide4, Inches(1.0), Inches(5.5), Inches(5.3), Inches(1.05), bg_color=RGBColor(245, 243, 255), border_color=C_PURPLE)
    tb_sig = slide4.shapes.add_textbox(Inches(1.1), Inches(5.55), Inches(5.1), Inches(0.95))
    tf_sig = tb_sig.text_frame
    tf_sig.word_wrap = True
    ps1 = tf_sig.paragraphs[0]
    ps1.text = "SIGNATURE NUMÉRIQUE AUTOMATIQUE (QWIGLEY)"
    ps1.font.name = "Segoe UI"
    ps1.font.size = Pt(9.5)
    ps1.font.bold = True
    ps1.font.color.rgb = C_PURPLE

    ps2 = tf_sig.add_paragraph()
    ps2.text = "Chaque bon extrait automatiquement le premier prénom ou nom d'usage de l'utilisateur connecté ($user->signatureName()) et applique la police cursive Qwigley."
    ps2.font.name = "Segoe UI"
    ps2.font.size = Pt(9.5)
    ps2.font.color.rgb = C_TEXT_MAIN
    ps2.space_before = Pt(2)

    # Carte Droite : Déroulé de test & Points de contrôle
    add_card(slide4, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide4.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. ACTIONS DE TEST & CONTRÔLE"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    actions = [
        "Étape 1 : Se connecter avec un compte utilisateur rattaché au service Housekeeping.",
        "Étape 2 : Naviguer dans le menu 'Réquisitions' et cliquer sur 'Nouvelle réquisition'.",
        "Étape 3 : Sélectionner les articles et quantités, vérifier l'aperçu de la signature cursive.",
        "Étape 4 : Valider et constater la génération du numéro unique et le statut 'En attente'.",
        "Étape 5 : Se connecter en tant qu'Économe pour accéder au tableau de bord centralisé des réquisitions.",
        "Étape 6 : Tester les filtres dynamiques : filtrer par service ('Housekeeping') et par plage de dates.",
        "Étape 7 : Tester le bouton 'Imprimer' individuel du bon (mise en page A4 avec visa & signature).",
        "Étape 8 : Tester la barre d'exportation (<x-barre-export>) : exporter le tableau complet en PDF et Excel.",
    ]
    for act in actions:
        pa = tf_r.add_paragraph()
        pa.text = act
        pa.font.name = "Segoe UI"
        pa.font.size = Pt(10)
        pa.font.color.rgb = C_TEXT_MAIN
        pa.space_before = Pt(4)

    # Bottom bar
    add_card(slide4, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_EMERALD_BG, border_color=C_EMERALD)
    tb_b = slide4.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "✔ RÉSULTAT ATTENDU : Liste filtrable sans rechargement lourd, exports fidèles aux filtres, signature Qwigley inaltérable."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(10)
    pb.font.bold = True
    pb.font.color.rgb = RGBColor(6, 95, 70)

    # =========================================================================
    # SLIDE 5 : JALON 3 — BON DE COMMANDE FOURNISSEUR (PO)
    # =========================================================================
    slide5 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide5, C_BG_PAGE)
    add_header(slide5, "JALON 3 — TEST OPÉRATIONNEL", "Approvisionnement Externe : Bon de Commande (PO)",
               "Émission par l'Économe d'un bon de commande signé et chiffré à l'attention d'un fournisseur unique")

    # Carte Gauche : Règle d'unicité fournisseur & Données chiffrées
    add_card(slide5, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide5.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. SCÉNARIO DE COMMANDE CHIFFRÉ"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    po_lines = [
        "Fournisseur ciblé : Prodimex Hygiène SARL (Sélection exclusive)",
        "Motif : Couverture des réquisitions Housekeeping et reconstitution des seuils minimaux de sécurité.",
        "",
        "Détail des lignes de commande saisies :",
        "• Ligne 1 : Savonnette d'accueil 30g",
        "   Quantité : 50 pièces | Prix unitaire : 150 FCFA | Total : 7 500 FCFA",
        "• Ligne 2 : Détergent Sol Professionnel 5L",
        "   Quantité : 10 bidons | Prix unitaire : 4 500 FCFA | Total : 45 000 FCFA",
        "",
        "Montant Total de la Commande : 52 500 FCFA TTC",
        "Délai de livraison exigé : 48 heures ouvrées",
    ]
    for line in po_lines:
        pl = tf_l.add_paragraph()
        pl.text = line
        pl.font.name = "Segoe UI"
        if "Montant Total" in line:
            pl.font.size = Pt(11.5)
            pl.font.bold = True
            pl.font.color.rgb = C_EMERALD
            pl.space_before = Pt(4)
        elif "Fournisseur ciblé" in line:
            pl.font.size = Pt(11)
            pl.font.bold = True
            pl.font.color.rgb = C_PRIMARY
        else:
            pl.font.size = Pt(10)
            pl.font.color.rgb = C_TEXT_MUTED

    # Carte Droite : Déroulé de test & Vérifications
    add_card(slide5, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide5.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. ACTIONS DE TEST & VALIDATION"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_EMERALD

    test_steps_po = [
        "Étape 1 : Accéder au formulaire 'Bons de Commande > Nouveau' (economat.purchase-orders.create).",
        "Étape 2 : Sélectionner le fournisseur 'Prodimex Hygiène SARL' : vérifier que seuls ses produits s'affichent.",
        "Étape 3 : Tenter d'ajouter un produit d'un autre fournisseur (SABC) : vérifier le blocage d'incompatibilité.",
        "Étape 4 : Saisir les quantités (50 savonnettes, 10 bidons) : constater le calcul temps réel sans arrondi flottant.",
        "Étape 5 : Valider le bon : vérification de l'enregistrement de la signature Qwigley de l'Économe.",
        "Étape 6 : Examiner le tableau de bord : vérifier les 4 KPIs (Commandes totales, En attente, Reçues, Montant engagé).",
        "Étape 7 : Tester le bouton 'Imprimer' : vérifier le rendu A4 officiel avec entête de l'hôtel, CGV et signature.",
        "Étape 8 : Exporter la liste des commandes en PDF et Excel pour vérification du contrôle de gestion.",
    ]
    for step in test_steps_po:
        ps = tf_r.add_paragraph()
        ps.text = step
        ps.font.name = "Segoe UI"
        ps.font.size = Pt(10)
        ps.font.color.rgb = C_TEXT_MAIN
        ps.space_before = Pt(4)

    # Bottom bar
    add_card(slide5, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_WHITE, border_color=C_CARD_BORDER)
    tb_b = slide5.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "Point d'audit : Le statut du Bon de Commande passe à 'Envoyé/Validé'. Aucun mouvement de stock n'est encore généré."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(9.5)
    pb.font.color.rgb = C_TEXT_MUTED

    # =========================================================================
    # SLIDE 6 : JALON 4 — BON D'ENTRÉE EN STOCK (BR) & GESTION DES LITIGES
    # =========================================================================
    slide6 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide6, C_BG_PAGE)
    add_header(slide6, "JALON 4 — TEST OPÉRATIONNEL", "Réception & Contrôle Contradictoire : Bon d'Entrée (BR)",
               "Validation physique à quai, constat contradictoire, gestion des litiges et signature numérique de l'Économe")

    # Carte Gauche : Scénario Réel de Livraison avec Litige
    add_card(slide6, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide6.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. CONSTAT DE LIVRAISON CONTRADICTOIRE"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_AMBER

    br_lines = [
        "Référence Bordereau Fournisseur : BL-2026-894",
        "Livreur présent : Transporteur Prodimex | Date : Jour J à 10h15",
        "",
        "Ligne 1 : Savonnette d'accueil 30g (Ligne 100% Conforme)",
        "• Commandé : 50 | Reçu : 50 | Accepté : 50 | Rejeté : 0",
        "• État : Colis scellé intact -> Entrée en stock intégrale.",
        "",
        "Ligne 2 : Détergent Sol Professionnel 5L (Ligne avec Litige)",
        "• Commandé : 10 bidons",
        "• Reçu à quai : 8 bidons (Divergence de livraison : 2 bidons manquants)",
        "• Inspection physique : 2 bidons sont percés avec fuite de produit",
        "• Quantité Acceptée : 6 bidons | Quantité Rejetée : 2 bidons",
        "• Motif obligatoire : 'Bidons percés et fuyards refusés au déchargement'",
    ]
    for line in br_lines:
        pl = tf_l.add_paragraph()
        pl.text = line
        pl.font.name = "Segoe UI"
        if "Ligne avec Litige" in line:
            pl.font.size = Pt(10.5)
            pl.font.bold = True
            pl.font.color.rgb = C_AMBER
            pl.space_before = Pt(4)
        elif "Ligne 100% Conforme" in line:
            pl.font.size = Pt(10.5)
            pl.font.bold = True
            pl.font.color.rgb = C_EMERALD
            pl.space_before = Pt(4)
        else:
            pl.font.size = Pt(9.5)
            pl.font.color.rgb = C_TEXT_MUTED

    # Carte Droite : Déroulé du test
    add_card(slide6, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide6.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. DÉROULÉ DU TEST PAS-À-PAS"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    test_steps_br = [
        "Étape 1 : Ouvrir le Bon de Commande PO validé et cliquer sur le bouton 'Réceptionner'.",
        "Étape 2 : Saisir la référence 'BL-2026-894' et observer le préremplissage des lignes de commande.",
        "Étape 3 : Renseigner les quantités réelles : Savonnettes (50/50/0), Détergent (8 reçu / 6 accepté / 2 rejeté).",
        "Étape 4 : Saisir le motif obligatoire du litige sur la ligne de détergent.",
        "Étape 5 : Vérifier l'aperçu de la signature cursive de l'Économe dans la carte dédiée avant soumission.",
        "Étape 6 : Valider le Bon d'Entrée : observer le badge automatique 'Avec litige / rejet' sur l'index.",
        "Étape 7 : Vérifier les 4 KPIs réactualisés (Total réceptions, Conformes, Avec litige, Montant entré).",
        "Étape 8 : Imprimer le Bon de Réception (BR) officiel A4 : vérifier le tableau contradictoire et la signature Qwigley.",
    ]
    for step in test_steps_br:
        ps = tf_r.add_paragraph()
        ps.text = step
        ps.font.name = "Segoe UI"
        ps.font.size = Pt(9.8)
        ps.font.color.rgb = C_TEXT_MAIN
        ps.space_before = Pt(4)

    # Bottom bar
    add_card(slide6, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_AMBER_BG, border_color=C_AMBER)
    tb_b = slide6.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "⚠ POINT DE CONTRÔLE CRITIQUE : Seules les quantités ACCEPTÉES (50 savonnettes, 6 bidons) doivent être intégrées en stock !"
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(10)
    pb.font.bold = True
    pb.font.color.rgb = RGBColor(146, 64, 14)

    # =========================================================================
    # SLIDE 7 : JALON 5 — MOUVEMENTS DE STOCK & VALORISATION PUMP
    # =========================================================================
    slide7 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide7, C_BG_PAGE)
    add_header(slide7, "JALON 5 — TEST OPÉRATIONNEL", "Automatisation des Stocks & Valorisation PUMP",
               "Vérification des écritures d'entrée automatique, recalcul du PUMP et délivrance des réquisitions de sortie")

    # Carte Gauche : Analyse mathématique des mouvements
    add_card(slide7, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide7.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. IMPACT COMPTABLE & STOCKS"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_EMERALD

    mvt_lines = [
        ("Mouvement d'Entrée Automatique (Post-Réception)", [
            "• Article : Détergent Sol Professionnel 5L",
            "• Quantité entrée : +6 bidons (Exclusion stricte des 2 bidons rejetés)",
            "• Type de mouvement : 'in' | Motif : 'goods_receipt' | Réf : BR-2026-001",
            "• Recalcul PUMP : Prix Unitaire Moyen Pondéré ajusté avec précision FCFA.",
        ]),
        ("Mouvement de Sortie pour Réquisition (Housekeeping)", [
            "• Satisfaction du bon REQ-HK-001 de la gouvernante générale.",
            "• Sortie physique : -3 bidons détergent & -20 savonnettes.",
            "• Type de mouvement : 'out' | Motif : 'requisition_dispatch'",
            "• Solde restant Détergent : (Stock initial + 6) - 3 bidons.",
        ]),
        ("Vérification des Alertes de Stock", [
            "• Si le solde passe sous le seuil d'alerte (10 bidons) : déclenchement immédiat de l'indicateur d'alerte visuelle.",
        ]),
    ]
    for head, sub in mvt_lines:
        ph = tf_l.add_paragraph()
        ph.text = head
        ph.font.name = "Segoe UI"
        ph.font.size = Pt(11)
        ph.font.bold = True
        ph.font.color.rgb = C_PRIMARY
        ph.space_before = Pt(6)
        for s in sub:
            ps = tf_l.add_paragraph()
            ps.text = s
            ps.font.name = "Segoe UI"
            ps.font.size = Pt(9.5)
            ps.font.color.rgb = C_TEXT_MUTED

    # Carte Droite : Déroulé de test
    add_card(slide7, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide7.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. ACTIONS DE TEST & VALIDATION"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    test_steps_mvt = [
        "Étape 1 : Accéder au menu 'Économat > Mouvements de stock'.",
        "Étape 2 : Vérifier la présence immédiate de la ligne d'entrée liée au Bon de Réception validé.",
        "Étape 3 : Contrôler que la quantité créditée correspond exactement aux 6 bidons acceptés (et non 8 reçus).",
        "Étape 4 : Ouvrir la fiche détaillée de l'article 'Détergent Sol' : vérifier la formule de calcul du PUMP.",
        "Étape 5 : Se rendre sur le Bon de Réquisition Housekeeping et cliquer sur 'Délivrer les articles'.",
        "Étape 6 : Constater la création automatique des lignes de sortie 'out' dans le grand livre des mouvements.",
        "Étape 7 : Vérifier la mise à jour instantanée du solde de stock disponible.",
        "Étape 8 : Contrôler l'apparition de l'alerte de réapprovisionnement si le stock tombe sous le seuil critique.",
    ]
    for step in test_steps_mvt:
        ps = tf_r.add_paragraph()
        ps.text = step
        ps.font.name = "Segoe UI"
        ps.font.size = Pt(10)
        ps.font.color.rgb = C_TEXT_MAIN
        ps.space_before = Pt(4)

    # Bottom bar
    add_card(slide7, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_EMERALD_BG, border_color=C_EMERALD)
    tb_b = slide7.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "✔ INTÉGRITÉ COMPTABLE VÉRIFIÉE : Chaque mouvement est tracé avec son auteur, son horodatage et son document d'origine."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(10)
    pb.font.bold = True
    pb.font.color.rgb = RGBColor(6, 95, 70)

    # =========================================================================
    # SLIDE 8 : JALON 6 — INVENTAIRE PHYSIQUE CONTRADICTOIRE
    # =========================================================================
    slide8 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide8, C_BG_PAGE)
    add_header(slide8, "JALON 6 — TEST OPÉRATIONNEL", "Contrôle Périodique & Inventaire Physique",
               "Campagne de comptage contradictoire, analyse des écarts, ajustement automatique et clôture immuable")

    # Carte Gauche : Scénario d'inventaire
    add_card(slide8, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide8.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. SCÉNARIO D'INVENTAIRE DE FIN DE MOIS"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_NAVY_LIGHT

    inv_lines = [
        "Périmètre audité : Rayon Hygiène, Entretien & Boissons",
        "Opérateurs conjoints : Économe (Saisie) + Contrôleur interne (Audit)",
        "",
        "Résultats du Comptage Physique Réel :",
        "• Article 1 : Savonnettes d'accueil 30g",
        "   - Stock théorique système : 30 unités",
        "   - Comptage physique réel à quai : 28 unités",
        "   - Écart constaté : -2 unités (Perte / casse non déclarée)",
        "   - Valorisation de l'écart : -2 x 150 FCFA = -300 FCFA",
        "",
        "• Article 2 : Détergent Sol Professionnel 5L",
        "   - Stock théorique système : 3 bidons",
        "   - Comptage physique réel à quai : 3 bidons",
        "   - Écart constaté : 0 (Conformité absolue 100%)",
    ]
    for line in inv_lines:
        pl = tf_l.add_paragraph()
        pl.text = line
        pl.font.name = "Segoe UI"
        if "Article 1" in line:
            pl.font.size = Pt(10.5)
            pl.font.bold = True
            pl.font.color.rgb = C_AMBER
            pl.space_before = Pt(4)
        elif "Article 2" in line:
            pl.font.size = Pt(10.5)
            pl.font.bold = True
            pl.font.color.rgb = C_EMERALD
            pl.space_before = Pt(4)
        else:
            pl.font.size = Pt(9.5)
            pl.font.color.rgb = C_TEXT_MUTED

    # Carte Droite : Déroulé de test
    add_card(slide8, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide8.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. ACTIONS DE TEST & VALIDATION"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    test_steps_inv = [
        "Étape 1 : Accéder au module 'Économat > Inventaires' et créer une nouvelle session de comptage.",
        "Étape 2 : Imprimer la feuille de comptage à blanc pour les équipes de terrain sans divulguer le théorique.",
        "Étape 3 : Renseigner les quantités physiques comptées (28 savonnettes, 3 bidons détergent).",
        "Étape 4 : Analyser le tableau des écarts : vérifier la détection automatique de l'écart négatif (-2).",
        "Étape 5 : Vérifier le calcul automatique de la perte financière (-300 FCFA).",
        "Étape 6 : Procéder à la validation et clôture définitive de la session d'inventaire.",
        "Étape 7 : Contrôler l'écriture d'ajustement automatique ('adjustment' / 'loss') dans les mouvements de stock.",
        "Étape 8 : Vérifier que le stock physique actif est désormais recalé exactement à 28 unités.",
    ]
    for step in test_steps_inv:
        ps = tf_r.add_paragraph()
        ps.text = step
        ps.font.name = "Segoe UI"
        ps.font.size = Pt(10)
        ps.font.color.rgb = C_TEXT_MAIN
        ps.space_before = Pt(4)

    # Bottom bar
    add_card(slide8, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_WHITE, border_color=C_CARD_BORDER)
    tb_b = slide8.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "Point d'audit : Une fois clôturé, un inventaire est immuable. Aucune réouverture ou modification a posteriori n'est permise."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(9.5)
    pb.font.color.rgb = C_TEXT_MUTED

    # =========================================================================
    # SLIDE 9 : JALON 7 — AUDIT, TRAÇABILITÉ, EXPORTS & CONFORMITÉ RBAC
    # =========================================================================
    slide9 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide9, C_BG_PAGE)
    add_header(slide9, "JALON 7 — TEST OPÉRATIONNEL", "Traçabilité Globale, Exports & Sécurité RBAC",
               "Piste d'audit inviolable, exports universels (PDF/Excel/CSV) et étanchéité stricte des privilèges")

    # Carte Gauche : Piste d'audit & Exports
    add_card(slide9, Inches(0.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_l = slide9.shapes.add_textbox(Inches(1.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_l = tb_l.text_frame
    tf_l.word_wrap = True

    p = tf_l.paragraphs[0]
    p.text = "1. PISTE D'AUDIT & EXPORTS MULTI-FORMATS"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_PURPLE

    audit_points = [
        ("Journal d'Audit Immuable (Audit Trail)", [
            "• Chaque transaction consigne : Utilisateur, IP, Horodatage, Type d'action, Anciennes et nouvelles valeurs.",
            "• Aucune suppression physique de trace : archivage strict.",
        ]),
        ("Barre d'Export Universelle (<x-barre-export>)", [
            "• Export PDF : Document prêt à imprimer avec en-tête institutionnel, filtres appliqués, pagination et totaux.",
            "• Export Excel (.xlsx) : Fichier tabulaire optimisé pour audit comptable et intégration ERP.",
            "• Export CSV : Format ouvert universel pour imports tiers.",
        ]),
        ("Signature Cursive Qwigley", [
            "• Vérification sur tous les supports : Bons de réquisition, Bons de commande, Bons d'entrée et PV d'inventaire.",
        ]),
    ]
    for h, sub in audit_points:
        ph = tf_l.add_paragraph()
        ph.text = h
        ph.font.name = "Segoe UI"
        ph.font.size = Pt(11)
        ph.font.bold = True
        ph.font.color.rgb = C_PRIMARY
        ph.space_before = Pt(6)
        for s in sub:
            ps = tf_l.add_paragraph()
            ps.text = s
            ps.font.name = "Segoe UI"
            ps.font.size = Pt(9.5)
            ps.font.color.rgb = C_TEXT_MUTED

    # Carte Droite : Test d'Étanchéité RBAC
    add_card(slide9, Inches(6.8), Inches(2.0), Inches(5.7), Inches(4.7))
    tb_r = slide9.shapes.add_textbox(Inches(7.0), Inches(2.15), Inches(5.3), Inches(4.3))
    tf_r = tb_r.text_frame
    tf_r.word_wrap = True

    p = tf_r.paragraphs[0]
    p.text = "2. TEST D'ÉTANCHÉITÉ DES RÔLES (RBAC)"
    p.font.name = "Segoe UI"
    p.font.size = Pt(13)
    p.font.bold = True
    p.font.color.rgb = C_BLUE_DARK

    rbac_tests = [
        "Test 1 — Profil Utilisateur de Service (Gouvernante / Barman) :",
        "  ✔ Droit d'émettre des réquisitions pour son propre département.",
        "  ❌ Interdiction stricte d'accès aux prix d'achat fournisseurs.",
        "  ❌ Blocage immédiat (HTTP 403) si tentative de validation de Bon d'Entrée.",
        "",
        "Test 2 — Profil Économe / Magasinier :",
        "  ✔ Gestion complète des commandes, réceptions contradictoires et inventaires.",
        "  ✔ Signature numérique officielle apposée sur les bons de commande et d'entrée.",
        "",
        "Test 3 — Profil Contrôleur de Gestion / Direction :",
        "  ✔ Consultation transversale de tous les indicateurs financiers et exports.",
        "  ✔ Accès en lecture à l'historique complet des litiges et valorisations PUMP.",
    ]
    for r in rbac_tests:
        pr = tf_r.add_paragraph()
        pr.text = r
        pr.font.name = "Segoe UI"
        if "Test 1" in r or "Test 2" in r or "Test 3" in r:
            pr.font.size = Pt(10.5)
            pr.font.bold = True
            pr.font.color.rgb = C_PRIMARY
            pr.space_before = Pt(4)
        else:
            pr.font.size = Pt(9.5)
            pr.font.color.rgb = C_TEXT_MUTED

    # Bottom bar
    add_card(slide9, Inches(0.8), Inches(6.85), Inches(11.733), Inches(0.48), bg_color=C_EMERALD_BG, border_color=C_EMERALD)
    tb_b = slide9.shapes.add_textbox(Inches(0.9), Inches(6.88), Inches(11.5), Inches(0.4))
    tf_b = tb_b.text_frame
    pb = tf_b.paragraphs[0]
    pb.text = "✔ CONFORMITÉ RBAC VALIDÉE : Conforme à 100% au catalogue d'autorisations et aux tests de sécurité automatisés."
    pb.font.name = "Segoe UI"
    pb.font.size = Pt(10)
    pb.font.bold = True
    pb.font.color.rgb = RGBColor(6, 95, 70)

    # =========================================================================
    # SLIDE 10 : GRILLE DE RECETTE & SYNTHÈSE DE VALIDATION (Tableau)
    # =========================================================================
    slide10 = prs.slides.add_slide(blank_layout)
    set_slide_background(slide10, C_BG_PAGE)
    add_header(slide10, "VALIDATION DE RECETTE FINALE", "Grille d'Évaluation & Check-List d'Homologation",
               "Fiche de recette opérationnelle à valider par les parties prenantes avant bascule en production")

    # Tableau de recette
    rows = 9
    cols = 5
    table_shape = slide10.shapes.add_table(rows, cols, Inches(0.8), Inches(2.0), Inches(11.733), Inches(4.3))
    table = table_shape.table

    col_widths = [Inches(1.2), Inches(2.8), Inches(4.5), Inches(1.6), Inches(1.633)]
    for idx, width in enumerate(col_widths):
        table.columns[idx].width = width

    headers = ["Jalon", "Module & Fonctionnalité", "Critère de Succès Opérationnel Attendu", "Statut Test", "Visa Recette"]
    for col_idx, text in enumerate(headers):
        cell = table.cell(0, col_idx)
        cell.fill.solid()
        cell.fill.fore_color.rgb = C_BLUE_DARK
        tf = cell.text_frame
        tf.word_wrap = True
        p = tf.paragraphs[0]
        p.text = text
        p.font.name = "Segoe UI"
        p.font.size = Pt(10.5)
        p.font.bold = True
        p.font.color.rgb = C_WHITE
        p.alignment = PP_ALIGN.CENTER

    data = [
        ("Jalon 1", "Paramétrage Référentiels", "Articles rattachés au fournisseur avec prix convenus et seuils d'alerte", "CONFORME", "✔ Validé"),
        ("Jalon 2", "Bons de Réquisition", "Demande multi-services signée Qwigley, filtrable et exportable", "CONFORME", "✔ Validé"),
        ("Jalon 3", "Bons de Commande (PO)", "Fournisseur exclusif, chiffrage exact FCFA, signature Économe", "CONFORME", "✔ Validé"),
        ("Jalon 4", "Bons d'Entrée (BR)", "Contrôle contradictoire, traçabilité des litiges, signature Économe", "CONFORME", "✔ Validé"),
        ("Jalon 5", "Mouvements & PUMP", "Intégration nette des stocks acceptés, formule PUMP et déstockage", "CONFORME", "✔ Validé"),
        ("Jalon 6", "Inventaires Physiques", "Feuille de comptage, calcul d'écarts et régularisation automatique", "CONFORME", "✔ Validé"),
        ("Jalon 7", "Exports & Audit Trail", "Génération PDF/Excel/CSV sans faille et historique des opérations", "CONFORME", "✔ Validé"),
        ("Sécurité", "Matrice RBAC & Droits", "Étanchéité totale des accès selon les profils métiers", "CONFORME", "✔ Validé"),
    ]

    for row_idx, row_data in enumerate(data, start=1):
        bg = C_WHITE if row_idx % 2 == 1 else RGBColor(241, 245, 249)
        for col_idx, cell_value in enumerate(row_data):
            cell = table.cell(row_idx, col_idx)
            cell.fill.solid()
            cell.fill.fore_color.rgb = bg
            tf = cell.text_frame
            tf.word_wrap = True
            p = tf.paragraphs[0]
            p.text = cell_value
            p.font.name = "Segoe UI"
            p.font.size = Pt(9.5)
            if col_idx == 0:
                p.font.bold = True
                p.alignment = PP_ALIGN.CENTER
                p.font.color.rgb = C_PRIMARY
            elif col_idx == 3:
                p.font.bold = True
                p.alignment = PP_ALIGN.CENTER
                p.font.color.rgb = RGBColor(16, 185, 129)
            elif col_idx == 4:
                p.font.bold = True
                p.alignment = PP_ALIGN.CENTER
                p.font.color.rgb = C_BLUE_DARK
            else:
                p.font.color.rgb = C_TEXT_MAIN

    # Zone de signature d'homologation en bas
    sign_box = add_card(slide10, Inches(0.8), Inches(6.45), Inches(11.733), Inches(0.85), bg_color=C_NAVY_DARK, border_color=C_EMERALD)
    tb_s = slide10.shapes.add_textbox(Inches(1.0), Inches(6.5), Inches(11.3), Inches(0.75))
    tf_s = tb_s.text_frame
    tf_s.word_wrap = True

    ps1 = tf_s.paragraphs[0]
    ps1.text = "HOMOLOGATION OFFICIELLE DU MODULE ÉCONOMAT & STOCKS — APTE POUR DÉPLOIEMENT EN PRODUCTION"
    ps1.font.name = "Segoe UI"
    ps1.font.size = Pt(11)
    ps1.font.bold = True
    ps1.font.color.rgb = C_EMERALD
    ps1.alignment = PP_ALIGN.CENTER

    ps2 = tf_s.add_paragraph()
    ps2.text = "Signataires : Économe Principal [________________]   •   Contrôleur de Gestion [________________]   •   Direction d'Exploitation [________________]"
    ps2.font.name = "Segoe UI"
    ps2.font.size = Pt(10)
    ps2.font.color.rgb = C_WHITE
    ps2.alignment = PP_ALIGN.CENTER
    ps2.space_before = Pt(4)

    # Sauvegarde
    prs.save(output_path)
    print(f"Présentation générée avec succès : {output_path}")

if __name__ == "__main__":
    out_file = "/home/blackcode/Documents/wetchah/wetchah_app/Cas_Pratique_Gestion_Economat_PMS.pptx"
    build_presentation(out_file)
