import { ASSET_BASE } from '../../utils/AssetPaths.js';
import React from 'react';

export default function StartupSplash() {
  return <div className="startup-splash" role="status" aria-label="Pokretanje aplikacije">
    <img src={ASSET_BASE + "drawable/radulovic_logo.svg"} width="208" height="208" alt="" />
  </div>;
}
