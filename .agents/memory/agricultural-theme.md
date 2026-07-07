---
name: Agricultural theme redesign
description: Complete visual overhaul of all EJS views from dark NVIDIA green to warm agricultural/rice-farm aesthetic
---

## Theme palette
- Background: `#F5EFE6` (warm cream)
- Primary amber gradient: `linear-gradient(135deg, #D4921F, #A0631A)`
- Cards: `#FFFFFF` with `border: 1px solid #F0E0C8` and soft box-shadow
- Text dark: `#3D2B1F`, mid: `#7D5A38`, muted: `#A87D50`
- Light bg for inputs: `#FBF5EC`

## 5-tab bottom nav (views/partials/menu.ejs)
- Tabs: Accueil (/) | Action (/investissement) | CENTER wheat (/salaire) | Équipe (/equipe) | Profil (/compte)
- Center btn: raised amber circle with `fas fa-wheat-awn`, margin-top: -22px
- Active-tab mapping: pass `page` local ('index', 'investissement', 'salaire', 'equipe', 'compte')
- faq/tuto/roue use `page: 'index'` (no dedicated tab)
- cadeau/depot/retrait/portefeuille use `page: 'compte'`

## Key decisions
- Hero sections use Unsplash orange/citrus image (mimics agricultural look without rice images requiring auth)
- Slot machine (roue.ejs) kept all GSAP animation logic and symbols array intact; symbol colors changed from white to amber
- salaire.ejs: popup still opens on "Récupérer" click (blacklist popup); color changed from red to amber/red
- compte.ejs copyId() takes explicit `btn` arg (not event.currentTarget) for reliable cross-browser behavior

**Why:** Design spec from 6 screenshots of "Sun Rice Mills" agricultural app, applied as full restyle while preserving all EJS data-bindings, French text, routes, and JS logic.
